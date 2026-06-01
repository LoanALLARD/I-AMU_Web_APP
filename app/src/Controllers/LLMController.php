<?php
    namespace Controllers;
//L'api doit prendre la forme suivante pour envoyer un prompt
    // de l'application vers le serveur ollama
    /*
    *   curl http://localhost:8082/api/generate -d '{
            "model": "llama3.2:1b",
            "prompt": "raconte moi une histoire",
            "stream": false,
            "format":"json"
            }'
    *
    format :                curl -X POST -d "data" URL
    commande cible :        curl -X POST -d '{
                                "model"   : "....",
                                "message" : "....",
                                "context" : "[..]"
                            }'
                            http://localhost:8085/chat
    *
    *
    *
    */


use Data\Database;
use Models\AiRepository;
use Domain\Ai;
use Domain\OllamaAdaptater;
use Models\InteractionRepository;
use Models\UserRepository;
use Models\ConversationRepository;

class LLMController{

    public function handleChat(){

        // raw data of the requeste
        $jsonRaw = file_get_contents('php://input');

        // Transaltion of the raw data to a associative array  
        $data = json_decode($jsonRaw, true);

        if (!$data || !isset($data['model']) || !isset($data['message'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Données invalides. "model" et "message" sont requis.']);
            return;
        }

        $modelName = $data['model'];     // "llama3.2:1b"
        $userMessage = $data['message']; 
        $context = $data['context'] ?? [];
        $user_email = $data['user_email'] ?? null;
        $conversation_id = $data['conversation_id'] ?? null;

        if ($user_email == null){
            throw new \Exception ("Error, wrong email");
            return;
        }

        $pdo = Database::getConnection();
        $aiRepository = new AiRepository($pdo);
        $userRepository = new UserRepository($pdo);

        $aiData = $aiRepository->getModelByName($modelName);
        $userData = $userRepository->getUserByEmail($user_email);

        if ($conversation_id == null) {
            $conversationRepository = new ConversationRepository($pdo);
            $conversationRepository->newConversation($userData['id'],1,"nouvelle conversation");
        }


        if (!$aiData) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "the model is not supported."]);
            return;
        }
        if (!$userData) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "the user is unknow."]);
            return;
        }

        switch ($aiData["adapter"]) {
        case "ollama":
            $adapter = new OllamaAdaptater($aiData["api_url"],$aiData["name"]);
            break;
        case "openAi":
            //code block;
            break;
        default:
            $adapter = null;
        }
        // if ($aiData["resource_id"] == NULL){ $resource = null;}else{$resource = $aiData["resource_id"];} 
        // if ($aiData["department_id"] == NULL){ $department = null;}else{$department = $aiData["department_id"];} 
        $ai = new Ai (
            $id = $aiData["id"],
            $department_id = $aiData["department_id"],
            $resource_id = $aiData["resource_id"],
            $aiData["name"],
            $aiData["version"],
            $aiData["provider"],
            $aiData["max_tokens"],
            $aiData["context_window"],
            $aiData["is_active"],
            $aiData["created_at"],
            $aiData["api_url"],
            $adapter,
        );

        $response = $ai->ask($userMessage, $context);

        if ($response != false){

            $response = json_decode($response);
            $interaction = new InteractionRepository($pdo);
            $output_tokens = count($response->context);
            $interaction->newInteration($ai->getId(),1,$userMessage,$response->response,200,$output_tokens);
        }

        header('Content-Type: application/json');
        echo json_encode(['response' => $response]);
        
    }
}
