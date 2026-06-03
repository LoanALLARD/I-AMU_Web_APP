<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use DateTimeImmutable;

/**
 * Input DTO consumed by {@see \App\Application\Services\UpdateSessionService}.
 *
 * Session type, access code and resource link are intentionally NOT part of
 * this DTO: type is immutable post-insert, the access code is rotated through
 * a dedicated flow, and switching the session to a different resource would
 * change its ownership semantics.
 */
final readonly class UpdateSessionRequest
{
    /**
     * @param list<int> $modelIds
     */
    public function __construct(
        public string $name,
        public ?DateTimeImmutable $startsAt,
        public int $durationMinutes,
        public array $modelIds,
        public ?string $prePrompt,
        public ?string $postPrompt,
        public ?string $instructions,
        public ?int $maxInputSize,
    ) {
    }
}
