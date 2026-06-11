<?php

namespace Domain;

interface LlmAdaptaterInterface {
    /**
     * Reçoit un message et un contexte, s'occupe de formater la requête
     * spécifique à l'API cible, l'exécute, et renvoie une chaîne standard.
     *
     * @param array<int, int> $context conversation context (provider token ids)
     */
    public function generate(string $message, array $context, ?string $preprompt,?string $posprompt,?bool $isTesting): string;

    public function formatMetadata(object $response): string;

    /**
     * @param array<string, mixed> $metaDataRaw
     * @return list<int>
     */
    public function readContextFromMetadata(array $metaDataRaw): array;
    /**
     * Same as generate() but streams the answer. Each response token is
     * passed to $onChunk as soon as it arrives. Returns the final aggregate so the caller can persist it.
     *
     * @param array<int, int> $context conversation context (provider token ids)
     * @param callable(string): void $onChunk
     * @return array{response: string, context: list<int>, prompt_eval_count: ?int, eval_count: ?int, total_duration: ?int}
     */
    public function generateStream(string $message, array $context, ?string $preprompt, ?string $posprompt, callable $onChunk): array;
}