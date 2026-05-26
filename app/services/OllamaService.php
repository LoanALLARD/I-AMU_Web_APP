<?php

namespace App\Services;

use App\Core\Application;

/**
 * Service de communication avec l'API Ollama (LLM local).
 * Gère l'envoi de prompts et la réception de réponses.
 */
class OllamaService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = Application::getInstance()->getConfig('ollama.host');
    }

    /**
     * Envoie un prompt à un modèle et retourne la réponse.
     *
     * @return array{response: string, input_tokens: int, output_tokens: int, latency: int}
     */
    public function chat(string $model, array $messages, ?string $systemPrompt = null): array
    {
        $payload = [
            // Le tag Ollama est case-sensitive et peut contenir ':' (ex: "llama3:8b").
            // On le préserve tel qu'il a été enregistré via syncFromOllama().
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ];

        if ($systemPrompt) {
            array_unshift($payload['messages'], [
                'role'    => 'system',
                'content' => $systemPrompt,
            ]);
        }

        $startTime = hrtime(true);
        $response = $this->request('/api/chat', $payload);
        $latency = (int) ((hrtime(true) - $startTime) / 1_000_000); // ms

        return [
            'response'      => $response['message']['content'] ?? '',
            'input_tokens'  => $response['prompt_eval_count'] ?? 0,
            'output_tokens' => $response['eval_count'] ?? 0,
            'latency'       => $latency,
        ];
    }

    /**
     * Envoie un prompt en streaming. Appelle $onChunk pour chaque morceau de texte reçu.
     *
     * @param callable $onChunk fonction(string $textChunk): void
     * @return array{response: string, input_tokens: int, output_tokens: int, latency: int}
     */
    public function chatStream(string $model, array $messages, ?string $systemPrompt, callable $onChunk): array
    {
        set_time_limit(300);

        if ($systemPrompt) {
            array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);
        }

        $payload = json_encode([
            'model'    => $model, // tag Ollama brut (case-sensitive, peut contenir ':')
            'messages' => $messages,
            'stream'   => true,
        ]);

        $startTime = hrtime(true);
        $fullResponse = '';
        $inputTokens = 0;
        $outputTokens = 0;

        $ch = curl_init($this->baseUrl . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$fullResponse, &$inputTokens, &$outputTokens, $onChunk) {
                // Ollama renvoie des objets JSON séparés par des retours à la ligne
                foreach (explode("\n", $data) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    $json = json_decode($line, true);
                    if (!$json) continue;

                    if (isset($json['message']['content'])) {
                        $chunk = $json['message']['content'];
                        $fullResponse .= $chunk;
                        $onChunk($chunk);
                    }
                    if (isset($json['prompt_eval_count'])) $inputTokens = $json['prompt_eval_count'];
                    if (isset($json['eval_count'])) $outputTokens = $json['eval_count'];
                }
                return strlen($data);
            },
        ]);

        $success = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($success === false) {
            throw new \RuntimeException("Impossible de contacter Ollama : $error");
        }

        $latency = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return [
            'response'      => $fullResponse,
            'input_tokens'  => $inputTokens,
            'output_tokens' => $outputTokens,
            'latency'       => $latency,
        ];
    }

    /**
     * Liste les modèles disponibles sur le serveur Ollama.
     */
    public function listModels(): array
    {
        $response = $this->request('/api/tags', [], 'GET');
        return $response['models'] ?? [];
    }

    /**
     * Vérifie si Ollama est accessible.
     */
    public function isAvailable(): bool
    {
        try {
            $this->request('/api/tags', [], 'GET');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Effectue une requête HTTP vers Ollama.
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->baseUrl . $endpoint;

        $options = [
            'http' => [
                'method'  => $method,
                'header'  => "Content-Type: application/json\r\n",
                'timeout' => 120,
            ],
        ];

        if ($method === 'POST' && !empty($data)) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new \RuntimeException("Impossible de contacter Ollama à l'adresse : $url");
        }

        return json_decode($result, true) ?? [];
    }
}
