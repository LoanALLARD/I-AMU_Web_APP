<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Domain\ValueObjects\SessionType;
use DateTimeImmutable;

/**
 * Input DTO consumed by {@see \App\Application\Services\CreateSessionService}.
 *
 * Aligned with the live `sessions` schema:
 *   - resource_id is mandatory (NOT NULL on the table)
 *   - a single `max_input_size` cap (the 3 token sub-caps were dropped
 *     when the schema was finalised)
 *   - `pre_prompt_override` / `post_prompt_override` for prompt steering
 */
final readonly class CreateSessionRequest
{
    /**
     * @param list<int> $modelIds Ids of models the teacher allows for this session.
     */
    public function __construct(
        public string $name,
        public SessionType $type,
        public int $resourceId,
        public ?DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public array $modelIds,
        public ?string $prePrompt,
        public ?string $postPrompt,
        public ?string $instructions,
        public ?int $maxInputSize,
        public ?string $accessCode = null,
    ) {
    }
}
