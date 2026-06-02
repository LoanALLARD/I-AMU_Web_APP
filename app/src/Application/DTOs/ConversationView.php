<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Read model for a conversation, surfaced to the chat shell so it can
 * display the conversation name and know which session it belongs to.
 */
final readonly class ConversationView
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $sessionId,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            isset($row['session_id']) ? (int) $row['session_id'] : null,
        );
    }
}
