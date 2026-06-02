<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Application\Ports\LlmProviderInterface;
use RuntimeException;

/**
 * Ollama implementation of {@see LlmProviderInterface}.
 *
 * Posts a non-streaming generate request to the Ollama HTTP API and
 * returns the raw response body verbatim — same wire contract as the
 * legacy ServeurFolder `OllamaAdaptater` it replaces.
 */
final class OllamaProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $url,
        private readonly string $modelName,
    ) {
    }

    public function generate(string $message, array $context): string
    {
        $payload = json_encode([
            'model'   => $this->modelName,
            'prompt'  => $message,
            'context' => $context,
            'stream'  => false,
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                throw new RuntimeException('Erreur cURL Ollama : ' . curl_error($ch));
            }

            return (string) $response;
        } finally {
            curl_close($ch);
        }
    }
}
