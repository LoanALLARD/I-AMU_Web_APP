<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Outcome of {@see \App\Application\Services\JoinSessionService::execute}.
 *
 * `alreadyJoined` is true when the student was already enrolled — the
 * HTTP layer reuses it to phrase the flash and reopen the existing
 * conversation instead of pretending a fresh join happened.
 */
final readonly class JoinSessionResult
{
    public function __construct(
        public int $conversationId,
        public bool $alreadyJoined,
        public string $sessionName,
    ) {
    }
}
