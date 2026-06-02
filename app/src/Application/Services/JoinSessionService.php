<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;
use App\Domain\Exceptions\SessionCancelledException;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;
use App\Domain\ValueObjects\AccessCode;
use App\Domain\ValueObjects\SessionStatus;
use RuntimeException;

/**
 * Use-case: a student joins a session with the access code they got from
 * the teacher (board, slide, oral).
 *
 * The service only validates the code/state and returns the session. The
 * actual creation of a Conversation linked to the joining student is
 * deferred to spec 03 (chat). This block (B) is purely about Sessions.
 */
final class JoinSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param string $rawCode  Code typed by the student; will be normalised
     *                         (strip dashes/whitespace, uppercase).
     */
    public function execute(string $rawCode, int $studentUserId): Session
    {
        $code    = AccessCode::fromUserInput($rawCode);
        $session = $this->sessions->findByAccessCode($code)
            ?? throw SessionNotFoundException::withAccessCode($code->value);

        $now      = $this->clock->now();
        $computed = $session->computedStatus($now);

        if ($computed === SessionStatus::Cancelled) {
            throw new SessionCancelledException("Cette session a été annulée.");
        }
        if ($computed === SessionStatus::Ended) {
            throw new RuntimeException("Cette session est terminée.");
        }
        if ($computed !== SessionStatus::Active) {
            throw new RuntimeException("Cette session n'est pas active actuellement.");
        }

        // The studentUserId is checked here even if we don't persist any
        // join row yet — so the call site is forced to pass an
        // authenticated user, and the contract is in place for spec 03.
        if ($studentUserId <= 0) {
            throw new \InvalidArgumentException("Student id must be positive.");
        }

        return $session;
    }
}
