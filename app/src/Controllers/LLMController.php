<?php
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

namespace Controllers;

use Data\Database;
use Models\AiRepository;
use Domain\Ai;
use Domain\OllamaAdaptater;

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
        $userMessage = $data['message']; // "c est un test"
        $context = $data['context'] ?? [];

        $pdo = Database::getConnection();
        $aiRepository = new AiRepository($pdo);

        $aiData = $aiRepository->getModelByName($modelName);

        if (!$aiData) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => "Le modèle demandé n'est pas supporté."]);
            return;
        }

        switch ($aiData["adaptater"]) {
        case "ollama":
            $adapter = new OllamaAdaptater($aiData["url"],$aiData["name"]);
            break;
        case "openAi":
            //code block;
            break;
        default:
            $adapter = null;
        }

        $ai = new Ai (
            $aiData["name"],
            $aiData["contextwindows"],
            $aiData["modelsize"],
            $aiData["compagny"],
            $aiData["url"],
            $adapter,
        );

        $response = $ai->ask($userMessage, $context);

        header('Content-Type: application/json');
        echo json_encode(['response' => $response]);
        
    }
}
