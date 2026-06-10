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

use Services\DocumentService;
use Services\ChatService;

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
        // Only sent when starting a brand-new chat from the "Nouvelle
        // conversation" button: tells us which environment to create the
        // conversation in (a session id, or 0/absent for free chat).
        $sessionIdInput = isset($data['session_id']) ? (int) $data['session_id'] : 0;
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
        if ($conversation_id == null) {                                     // Blank chat: persist the conversation now (deferred creation).
            if ($sessionIdInput > 0) {
                // Session environment: delegate to ChatService so the session
                // is validated (active, student still enrolled) and the row is
                // named "SESSION - CODE #N" like the previous up-front path.
                try {
                    $newId = (new ChatService($pdo))->newSessionConversation(
                        $userId,
                        $sessionIdInput,
                        (int) $aiData['id']
                    );
                } catch (\Throwable $e) {
                    header('Content-Type: application/json');
                    http_response_code(422);
                    echo json_encode(['error' => $e->getMessage()]);
                    return;
                }
                $conversationData = $conversationRepository->getConversationByUserIdAndConversationId($userId, $newId);
            } else {
                // Free environment: name the conversation after the first message.
                $conversationData = $conversationRepository->newConversation(
                    $userId,
                    (int) $aiData['id'],
                    null,
                    $nameConversation
                );
            }
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

        // Inject the conversation's documents (phase 2) into the system prompt
        // so the model takes them into account. Empty when none are attached.
        $documentService = new DocumentService($pdo);
        $docsBlock = $documentService->buildSystemContext((int) $conversationData['id']);
        if ($docsBlock !== '') {
            $preprompt = ($preprompt !== null && trim($preprompt) !== '')
                ? trim($preprompt) . "\n\n" . $docsBlock
                : $docsBlock;
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
        // Switch to a streamed response. From here we stop sending a single
        // JSON body: each token is pushed as a Server-Sent Event the moment
        // Ollama produces it. All validation errors above already returned
        // a normal JSON error before reaching this point.
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        ob_implicit_flush(true);

        $onChunk = static function (string $piece): void {
            echo "event: token\n";
            echo 'data: ' . json_encode(['text' => $piece]) . "\n\n";
            flush();
        };

        try {
            $result = $ai->askStream($userMessage, $context, $preprompt, $postprompt, $onChunk);
        } catch (\Throwable $e) {
            echo "event: error\n";
            echo 'data: ' . json_encode(['error' => 'Le modele est indisponible.']) . "\n\n";
            flush();
            return;
        }

        // Persist the interaction (every conversation is persisted).
        $boundDocs = [];
        $interactionId = null;
        if ($conversationData !== null && $result['response'] !== '') {
            $interaction = new InteractionRepository($pdo);
            $interactionData = $interaction->newInteration(
                (int) $conversationData['id'],
                $userMessage,
                $result['response'],
                $result['prompt_eval_count'],
                $result['eval_count']
            );
            // Store the provider context so the next turn keeps the thread.
            $meta_data = json_encode([
                'context'        => $result['context'],
                'total_duration' => null,
                'done_reason'    => 'stop',
            ]);
            $interaction->setContext($meta_data, $interactionData['id']);

            // Tie the conversation's pending documents to this message and
            // collect them so the UI can show them under the message.
            $interactionId = (int) $interactionData['id'];
            $documentService->bindPendingToInteraction((int) $conversationData['id'], $interactionId);
            foreach ($documentService->documentsForInteraction($interactionId) as $doc) {
                $boundDocs[] = [
                    'id'     => $doc->id(),
                    'name'   => $doc->originalName(),
                    'size'   => $doc->humanSize(),
                    'kind'   => $doc->kindLabel(),
                    'status' => $doc->status()->value,
                ];
            }
        }

        // Final event: metadata the UI needs once generation is done. The
        // interaction id lets the chat wire its satisfaction thumbs (feedback).
        echo "event: done\n";
        echo 'data: ' . json_encode([
                'prompt_eval_count' => $result['prompt_eval_count'],
                'eval_count'        => $result['eval_count'],
                'conversation_id'   => $conversationData['id'] ?? null,
                'conversation_name' => $conversationData['name'] ?? null,
                'interaction_id'    => $interactionId,
                'documents'         => $boundDocs,
            ]) . "\n\n";
        flush();
    }

    /**
     * Records the satisfaction rating a student gives to one of their own AI
     * responses (the chat thumbs up/down). Persists `interactions.user_feedback`
     * scoped to the authenticated user's own conversations, so nobody can rate
     * another student's interaction. Expects JSON { interaction_id, value }
     * with value in {1, 0, -1}.
     */
    public function recordFeedback(): void
    {
        header('Content-Type: application/json');

        $data = json_decode((string) file_get_contents('php://input'), true);
        $interactionId = isset($data['interaction_id']) ? (int) $data['interaction_id'] : 0;
        $value         = isset($data['value']) ? (int) $data['value'] : 99;

        if ($interactionId <= 0 || !in_array($value, [1, 0, -1], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Requête invalide.']);
            return;
        }

        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié.']);
            return;
        }

        $pdo = Database::getConnection();
        $ok  = (new InteractionRepository($pdo))->setFeedback($interactionId, $userId, $value);

        if (!$ok) {
            http_response_code(404);
            echo json_encode(['error' => 'Interaction introuvable.']);
            return;
        }

        echo json_encode(['ok' => true, 'value' => $value]);
    }
}