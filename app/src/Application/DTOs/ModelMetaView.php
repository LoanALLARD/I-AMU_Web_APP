<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Read-side flat view of a LLM model.
 *
 * Hydrated from rows of the `models` table (singular per Block B; renamed
 * plural by the live schema). Kept minimal because the full Model aggregate
 * (Ollama sync, scoping per resource/department) belongs to spec 03.
 */
final readonly class ModelMetaView
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $version,
        public ?int $contextWindow,
        public bool $isActive,
    ) {
    }

    /**
     * @param array{id:int, name:string, version:?string, context_window:?int, is_active:bool} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            name:          (string) $row['name'],
            version:       $row['version'] !== null ? (string) $row['version'] : null,
            contextWindow: $row['context_window'] !== null ? (int) $row['context_window'] : null,
            isActive:      (bool) $row['is_active'],
        );
    }
}
