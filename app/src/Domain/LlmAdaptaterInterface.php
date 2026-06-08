<?php

declare(strict_types=1);

namespace Domain;

interface LlmAdaptaterInterface {
    /**
     * Takes a message and a context, formats the request
     * * specific to the target API, executes it, and returns a standard string.
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
}