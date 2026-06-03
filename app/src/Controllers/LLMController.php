<?php

namespace Controllers;

use Data\Database;

use Domain\Conversation;
use Domain\Ai;
use Domain\OllamaAdaptater;

use Models\AiRepository;
use Models\InteractionRepository;
use Models\UserRepository;
use Models\ConversationRepository;

class LLMController{

    public function handleChat(){

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
        $user_email = $data['user_email'] ?? null;
        $conversation_id = $data['conversation_id'] ?? null;

        if ($user_email == null){
            throw new \Exception ("Error, wrong email");
            return;
        }

        $pdo = Database::getConnection();                                   // Instance of the database

        $userRepository = new UserRepository($pdo);   
        $userData = $userRepository->getUserByEmail($user_email);
        
        if ($userData == null){
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "the user is unknow."]);
            // throw new \Exception ("Error, wrong email");
            return;
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

        $conversationRepository = new ConversationRepository($pdo);
        $nameConversation = "nouvelle conversation";
        if ($conversation_id == null) { 
            // If the conversation isn't given create new one
            $conversationData = $conversationRepository->newConversation(
                $userData['id'],
                1,
                $aiData['id'],
                $nameConversation
            );
            $context = [];
        } else {
            // else recover the conversation and check if it's own by the same user
            $conversationData = $conversationRepository->getConversationByUserId(   
                $userData['id'],
                $conversation_id,
            );               
        }                                                           
                
        if ($conversationData == null){
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "this user has no conversation corresponding with id :". $conversation_id ]);
            // throw new \Exception ("Error, this user has no conversation corresponding");
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

        // read from the database all the context of the conversation
        $metadata = $conversationRepository->getContextByConversationIdAndUserId($conversationData['id'],$userData['id']);
        // then translate it trought the adapter of the api used
        if ($metadata){
            $context = $adapter->readContextFromMetadata($metadata);
        }
        
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
        $responseRaw = $ai->ask($userMessage, $context);
        $response = json_decode($responseRaw);

        if ($response === null || (is_object($response) && isset($response->error))) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['message' => "The model is not available", "error"=>$response ]);
            return;
        }

        if ($response != false){
            $interaction = new InteractionRepository($pdo);
            $output_tokens = count($response->context);
            $interactionData = $interaction->newInteration($conversationData['id'],$userMessage,$response->response,200,$output_tokens);
            $meta_data = $adapter->formatMetadata($response);
            $var=$interaction->setContext($meta_data,$interactionData['id']);
        }

        header('Content-Type: application/json');
        echo json_encode(['response' => $response->response]);
        
    }
}
