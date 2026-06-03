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
}