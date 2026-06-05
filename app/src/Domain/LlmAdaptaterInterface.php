<?php

namespace Domain;

interface LlmAdaptaterInterface {
    /**
     * Reçoit un message et un contexte, s'occupe de formater la requête
     * spécifique à l'API cible, l'exécute, et renvoie une chaîne standard.
     *
     * @param array<int, int> $context conversation context (provider token ids)
     */
    public function generate(string $message, array $context, ?string $preprompt,?string $posprompt): string;

    public function formatMetadata(object $response): string;

    /**
     * @param array<string, mixed> $metaDataRaw
     * @return list<int>
     */
    public function readContextFromMetadata(array $metaDataRaw): array;
}