<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Minimal model configuration needed to route a chat request to the
 * right LLM backend. Hydrated from the `models` table by
 * {@see \App\Application\Ports\LlmModelRepositoryInterface}.
 */
final readonly class LlmModelConfig
{
    public function __construct(
        public string $name,
        public string $adapter,
        public string $apiUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['name'],
            (string) $row['adapter'],
            (string) $row['api_url'],
        );
    }
}
