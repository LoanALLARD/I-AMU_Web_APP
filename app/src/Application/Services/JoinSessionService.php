<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\JoinSessionResult;
use App\Application\Ports\ClockInterface;
use App\Application\Ports\ConversationRepositoryInterface;
use App\Application\Ports\EnrollmentRepositoryInterface;
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
 * Validates the code/state, then enrolls the student and opens the
 * session-bound conversation. The flow is idempotent: a student who
 * already joined keeps their existing enrollment and conversation instead
 * of getting a duplicate (the enrollments PK enforces this physically).
 */
final class JoinSessionService
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly ConversationRepositoryInterface $conversations,
    ) {
    }

    /**
     * @param string $rawCode  Code typed by the student; will be normalised
     *                         (strip dashes/whitespace, uppercase).
     */
    public function execute(string $rawCode, int $studentUserId): JoinSessionResult
    {
        if ($studentUserId <= 0) {
            throw new \InvalidArgumentException("Student id must be positive.");
        }

        $code    = AccessCode::fromUserInput($rawCode);
        $session = $this->sessions->findByAccessCode($code)
            ?? throw SessionNotFoundException::withAccessCode($code->value);

        $now      = $this->clock->now();
        $computed = $session->computedStatus($now);

        if ($computed === SessionStatus::Cancelled) {
            throw new SessionCancelledException(
                "Cette session a été annulée : vous ne pouvez pas la rejoindre."
            );
        }
        if ($computed !== SessionStatus::Active) {
            // Surface WHY the session is not joinable, per state.
            throw new RuntimeException(match ($computed) {
                SessionStatus::Ended     => "Cette session est terminée : vous ne pouvez plus la rejoindre.",
                SessionStatus::Scheduled => "Cette session n'a pas encore commencé : revenez quand l'enseignant l'aura démarrée.",
                SessionStatus::Draft     => "Cette session n'est pas encore ouverte aux étudiants.",
                default                  => "Cette session n'est pas disponible actuellement.",
            });
        }

        $sessionId = $session->id();

        // For a student, students.id == users.id (vertical inheritance),
        // so the same numeric id keys both enrollments and conversations.
        $alreadyJoined = $this->enrollments->exists($studentUserId, $sessionId);
        if (!$alreadyJoined) {
            $this->enrollments->enroll($studentUserId, $sessionId);
        }

        $conversationId = $this->conversations->findIdByUserAndSession($studentUserId, $sessionId)
            ?? $this->conversations->create(
                $studentUserId,
                $sessionId,
                'SESSION - ' . $session->accessCode()->formatted(),
            );

        return new JoinSessionResult($conversationId, $alreadyJoined, $session->name());
    }
}
