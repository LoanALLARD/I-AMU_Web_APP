<?php
namespace App\Domain;

class OllamaAdapter implements LlmAdapterInterface {
    private string $url;
    private string $modelName;

    public function __construct(string $url, string $modelName) {
        $this->url = $url;
        $this->modelName = $modelName;
    }

    public function generate(string $message, array $context): string {
        // C'est ICI qu'on gère le format spécifique à Ollama
        $payload = json_encode([
            "model" => $this->modelName,
            "prompt" => $message,
            "context" => $context,
            "stream" => false
        ]);

        // Code cURL...
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,"http://i-amu_web_app-php-app-1:11434/api/generate");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $response = curl_exec($ch);

            var_dump($response);
            return $textResultat;
        } catch (\Throwable $th) {
            throw $th;
        } finally {
            curl_close($ch);
        }
    }
}