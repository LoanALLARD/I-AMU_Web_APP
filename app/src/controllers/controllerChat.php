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

    namespace Controller;
    
    class controllerChat{
        public function generate_request(string $model, string $message, array $context){
            var_dump($model);
            var_dump($message);
            var_dump($context);
        }

        public function handleChat():void {
            $inputJSON = file_get_contents('php://input');
            var_dump($inputJSON);
            $input = json_decode($inputJSON,true);
            var_dump($input);
        }
    }
