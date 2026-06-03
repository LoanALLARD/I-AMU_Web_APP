<?php

namespace Domain;

use Domain\LlmAdaptaterInterface;

class OllamaAdaptater implements LlmAdaptaterInterface {
    private string $url;
    private string $modelName;

    public function __construct(string $url, string $modelName) {
        $this->url = $url;
        $this->modelName = $modelName;
    }

    public function generate(string $message, array $context): string {
        $payload = json_encode([
            "model" => $this->modelName,
            "prompt" => $message,
            "context" => $context,
            "stream" => false
        ]);

        // Code cURL...
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);
            $response = curl_exec($ch);

            if ($response === false) {
                throw new \Exception("Erreur cURL : " . curl_error($ch));
            }

            return $response;

        } catch (\Throwable $th) {
            throw $th;
        } finally {
            curl_close($ch);
        }
    }

    public function formatMetadata(object $response){
        // json-- Ollama
        // {
        // "context": [128006, 9125, 128007, ...],
        // "done_reason": "stop",
        // "total_duration": 6337226156
        // }

        // echo json_encode(['message' => "The model is not available", "error"=>$response ]);
        $contextFormated = json_encode([
            'context'=>$response->context,
            'total_duration'=>$response->total_duration,
            'done_reason'=>$response->done_reason
        ]);
        header('Content-Type: application/json');
        return json_encode($contextFormated);
    }

    public function readContextFromMetadata(array $metaDataRaw) : array{
        $rawString = $metaDataRaw['api_metadata'];
        
        $decodedOnce = json_decode($rawString, true);
        
        if (is_string($decodedOnce)) {
            $data = json_decode($decodedOnce, true);
        } else {
            $data = $decodedOnce;
        }

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !isset($data["context"])) {
            return null;
        }

        return $data['context'];
    }
}