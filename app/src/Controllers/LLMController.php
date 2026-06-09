<?php

namespace Controllers;

use Data\Database;

use Domain\Ai;
use Domain\OllamaAdaptater;

use Models\AiRepository;
use Models\InteractionRepository;
use Models\ConversationRepository;
use Models\SessionRepository;
use Models\EnrollmentRepository;

class LLMController{

    public function handleChat(): void {

        // raw data of the request
        $jsonRaw = file_get_contents('php://input');

        // Transaltion of the raw data to a associative array  
        $data = json_decode($jsonRaw, true);
        if (!$data || !isset($data['model']) || !isset($data['message'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Données invalides. "model" et "message" sont requis.']);
            return;
        }

        $modelName = $data['model'];
        $userMessage = $data['message'];
        $context = [];

        $conversation_id = $data['conversation_id'] ?? null;
        // Identify the user from the authenticated session (set at login),
        // never from the client payload. No email lookup needed.
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        // $userId = 1;
        
        if ($userId <= 0) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié.']);
            return;
        }

        // Roles with no chat access: refuse even a forged request.
        $roles = $_SESSION['roles'] ?? [];
        if (in_array('researcher', $roles, true) || in_array('department_admin', $roles, true)) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé.']);
            return;
        }

        $pdo = Database::getConnection();                                   // Instance of the database

        // Exam lockdown: a student inside a running exam may only post to
        // their exam conversation. Free chat (no id) or any other
        // conversation is rejected, so the locked UI cannot be bypassed by
        // calling this endpoint directly.
        if (in_array('student', $_SESSION['roles'] ?? [], true)) {
            $lock = (new \Services\ExamLockService($pdo))->activeLockFor($userId);
            if ($lock !== null) {
                $targetId = $conversation_id !== null ? (int) $conversation_id : null;
                if ($lock['conversationId'] === null || $targetId !== $lock['conversationId']) {
                    header('Content-Type: application/json');
                    http_response_code(403);
                    echo json_encode(['error' => "Examen en cours : seule la conversation de l'examen est autorisée."]);
                    return;
                }
            }
        }

        $aiRepository = new AiRepository($pdo);
        $aiData = $aiRepository->getModelByName($modelName);                // Read Data from the DataBase

        if ($aiData == null){
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "the model is not supported."]);
            // throw new \Exception ("Error, model : ".$modelName." is unknow");
            return;
        }        

        // A conversation id is only sent on a session-bound chat. We resolve
        // it (with an ownership check) so the interaction can be persisted.
        // Free chat (no id) runs without persistence.
        $conversationData = null;
        $conversationRepository = new ConversationRepository($pdo);
        $nameConversation = mb_substr($userMessage, 0, 40);
        if ($conversation_id == null) {                                     // If the conversation isn't given create new one
            $conversationData = $conversationRepository->newConversation(
                $userId,
                (int) $aiData['id'],
                null,
                $nameConversation
            );
            $context = [];
        } else {
            // else recover the conversation and check if it's own by the same user
            $conversationData = $conversationRepository->getConversationByUserIdAndConversationId(
                $userId,
                $conversation_id,
            );       
            if ($conversationData !== null && preg_match('/^Conversation #\d+$/', $conversationData['name'])) {
                $conversationRepository->rename(
                    $userId,
                    (int) $conversationData['id'],
                    $nameConversation
                );
                $conversationData['name'] = $nameConversation;
            }        
        }                                                           
                
        if ($conversationData == null){
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "this user has no conversation corresponding with id :". $conversation_id ]);
            // throw new \Exception ("Error, this user has no conversation corresponding");
            return;
        }    

        // A student deactivated by the teacher cannot send in the session
        // (server-side enforcement of the "disconnect").
        if ($conversationData["session_id"] != null) {
            $enrollments = new EnrollmentRepository($pdo);
            $sid = (int) $conversationData["session_id"];
            if ($enrollments->exists($userId, $sid) && !$enrollments->isActive($userId, $sid)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => "Vous avez été retiré de cette session par l'enseignant."]);
                return;
            }
        }

        // Get the preprompt of the session
        $preprompt = null;
        $postprompt = null;
        if ($conversationData["session_id"] !=null){
            $sessionRepo = new SessionRepository($pdo);
            // The requested model must be authorized for the session.
            $allowedModelIds = $sessionRepo->authorizedModelIdsOf((int) $conversationData["session_id"]);
            if (!in_array((int) $aiData['id'], $allowedModelIds, true)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => "Ce modèle n'est pas autorisé pour cette session."]);
                return;
            }
            $sessionRow  = $sessionRepo->findById((int) $conversationData["session_id"]);
            // Per-prompt character limit (max_input_size). Counts characters of the raw user message
            $maxInputSize = isset($sessionRow['max_input_size']) && $sessionRow['max_input_size'] !== null
                ? (int) $sessionRow['max_input_size']
                : null;
            if ($maxInputSize !== null && mb_strlen($userMessage) > $maxInputSize) {
                header('Content-Type: application/json');
                http_response_code(422);
                echo json_encode(['error' => "Message trop long : " . mb_strlen($userMessage) . "/$maxInputSize caractères."]);
                return;
            }
            // Enforce the teacher-set request limit per session, blocking further prompts once reached (NULL denotes unlimited).
            $max_tokens  = isset($sessionRow['max_tokens']) && $sessionRow['max_tokens'] !== null
                ? (int) $sessionRow['max_tokens']
                : null;
            if ($max_tokens  !== null) {
                $used = $sessionRepo->tokenUsageForStudent($userId, (int) $conversationData["session_id"]);
                if ($used  >= $max_tokens ) {
                    header('Content-Type: application/json');
                    http_response_code(429);
                    echo json_encode(['error' => "Limite de tokens atteinte pour cette session ($used/$max_tokens)."]);
                    return;
                }
            }
            $promptRaw = $sessionRepo->getPreAndPostPromptBySessionId($conversationData["session_id"]);
            $preprompt = $promptRaw["pre_prompt_override"];
            $postprompt = $promptRaw["post_prompt_override"];
        }

        $metadata = $conversationRepository->getContextByConversationIdAndUserId($conversationData['id'], $userId);

        $adapter = null;
        switch ($aiData["adapter"]) {
            case "ollama":
                $adapter = new OllamaAdaptater($aiData["api_url"], $aiData["name"]);
                break;
            case "openAi":
                // not implemented yet
                break;
        }
        if ($adapter === null) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => "Adaptateur non supporté."]);
            return;
        }

        // read from the database all the context of the conversation
        $metadata = $conversationRepository->getContextByConversationIdAndUserId($conversationData['id'],$userId);
        // then translate it trought the adapter of the api used
        if ($metadata){
            $context = $adapter->readContextFromMetadata($metadata);
        }

        // Recover Param from Session

        $ai = new Ai (
            $aiData["id"],
            $aiData["department_id"],
            $aiData["resource_id"],
            $aiData["name"],
            $aiData["size"],
            $aiData["provider"],
            $aiData["context_window"],
            $aiData["is_active"],
            $aiData["is_shareable"],
            $aiData["api_url"],
            $adapter,
        );
        $responseRaw = $ai->ask($userMessage, $context,$preprompt,$postprompt);
        $response = json_decode($responseRaw);

        if ($response === null || (is_object($response) && isset($response->error))) {
            $detail = is_object($response) && isset($response->error)
                ? (string) $response->error
                : 'Le modele est indisponible.';
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $detail]);
            return;
        }

        // Persist the interaction only for a session-bound conversation.
        if ($conversationData !== null && $response !== false && isset($response->response)) {
            $interaction = new InteractionRepository($pdo);
            $input_tokens  = isset($response->prompt_eval_count) ? (int) $response->prompt_eval_count : null;
            $output_tokens = isset($response->eval_count) ? (int) $response->eval_count : null;

            $interactionData = $interaction->newInteration(
                (int) $conversationData['id'],
                $userMessage,
                (string) $response->response,
                $input_tokens,
                $output_tokens
            );
            $meta_data = $adapter->formatMetadata($response);
            $var=$interaction->setContext($meta_data,$interactionData['id']);
        }

        header('Content-Type: application/json');
        echo json_encode([
            'response'          => $response->response,
            'prompt_eval_count' => $response->prompt_eval_count ?? null,
            'eval_count'        => $response->eval_count ?? null,
            'conversation_id'   => $conversationData['id'] ?? null,
            'conversation_name' => $conversationData['name'] ?? null,
        ]);
        
    }
}

