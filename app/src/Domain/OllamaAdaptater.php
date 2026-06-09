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

    /**
     * @param array<int, int> $context conversation context (Ollama token ids)
     */
    public function generate(string $message, array $context, ?string $preprompt, ?string $posprompt,?bool $isTesting): string {
        if ($posprompt != null){
            $message = $message . $posprompt;
        };
        $payload = json_encode([
            "model" => $this->modelName,
            "prompt" => $message,
            "context" => $context,
            "system"   => $preprompt,
            "stream" => false
        ]);

        if ($isTesting) {
            return $payload;
        };

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

    /**
     * Same as generate() but streams the answer. Each response token is passed to $onChunk as soon as it arrives.
     *
     * @param array<int, int> $context conversation context (provider token ids)
     * @param callable(string): void $onChunk
     * @return array{response: string, context: list<int>, prompt_eval_count: ?int, eval_count: ?int}
     */
    public function generateStream(string $message, array $context, ?string $preprompt, ?string $posprompt, callable $onChunk): array {
        if ($posprompt !== null) {
            $message = $message . $posprompt;
        }

        $payload = json_encode([
            "model"   => $this->modelName,
            "prompt"  => $message,
            "context" => $context,
            "system"  => $preprompt,
            "stream"  => true
        ]);

        $full = '';
        $finalContext = [];
        $promptEval = null;
        $evalCount = null;
        $buffer = '';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);
        // Receive the body progressively instead of buffering it all.
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, string $data) use (
            &$buffer, &$full, &$finalContext, &$promptEval, &$evalCount, $onChunk
        ): int {
            $buffer .= $data;
            // Ollama emits one JSON object per line. Only handle complete
            // lines, keep any trailing partial line for the next chunk.
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '') {
                    continue;
                }
                $obj = json_decode($line, true);
                if (!is_array($obj)) {
                    continue;
                }
                if (isset($obj['response']) && $obj['response'] !== '') {
                    $full .= $obj['response'];
                    $onChunk($obj['response']);
                }
                if (!empty($obj['done'])) {
                    $finalContext = isset($obj['context']) && is_array($obj['context'])
                        ? array_values(array_map('intval', $obj['context']))
                        : [];
                    $promptEval = isset($obj['prompt_eval_count']) ? (int) $obj['prompt_eval_count'] : null;
                    $evalCount  = isset($obj['eval_count']) ? (int) $obj['eval_count'] : null;
                }
            }
            return strlen($data);
        });

        try {
            if (curl_exec($ch) === false) {
                throw new \Exception("Erreur cURL : " . curl_error($ch));
            }
        } finally {
            curl_close($ch);
        }

        return ['response' => $full, 'context' => $finalContext, 'prompt_eval_count' => $promptEval, 'eval_count' => $evalCount];
    }

    public function formatMetadata(object $response): string {
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
        return json_encode($contextFormated) ?: '';
    }

    /**
     * @param array<string, mixed> $metaDataRaw
     * @return list<int>
     */
    public function readContextFromMetadata(array $metaDataRaw): array
    {
        $rawString = $metaDataRaw['api_metadata'];

        $decodedOnce = json_decode($rawString, true);

        if (is_string($decodedOnce)) {
            $data = json_decode($decodedOnce, true);
        } else {
            $data = $decodedOnce;
        }

        if (
            json_last_error() !== JSON_ERROR_NONE
            || !is_array($data)
            || !isset($data["context"])
            || !is_array($data["context"])
        ) {
            return [];
        }

        return array_values(array_map('intval', $data["context"]));
    }
}