<?php

declare(strict_types=1);

namespace App\Application\Ports;

/**
 * Port for a Large Language Model backend.
 *
 * Implementations live in App\Infrastructure\Llm (e.g. OllamaProvider).
 * Receives a user message plus prior context, formats the provider-specific
 * request, executes it and returns the raw provider response body.
 */
interface LlmProviderInterface
{
    /**
     * @param array<int|string, mixed> $context
     */
    public function generate(string $message, array $context): string;
}
