<?php

declare(strict_types=1);

namespace Services;

use DateTimeImmutable;
use DateTimeInterface;
use Domain\Session;
use Domain\SessionType;
use Models\ConversationRepository;
use Models\SessionRepository;
use PDO;

/**
 * Detects whether a student is currently locked inside a running exam.
 *
 * A student is locked when enrolled in an EXAM-type session whose computed
 * status is Active (the time-based expiry is resolved by Domain\Session).
 * While locked, the student may only reach their exam conversation; every
 * other chat target is denied at the controller layer.
 *
 * The lock releases on its own once the session is no longer active (teacher
 * ended it, cancelled it, or the scheduled time elapsed).
 */
class ExamLockService
{
    private SessionRepository $sessions;
    private ConversationRepository $conversations;

    public function __construct(PDO $pdo)
    {
        $this->sessions      = new SessionRepository($pdo);
        $this->conversations = new ConversationRepository($pdo);
    }

    /**
     * Returns the active exam lock for the given user, or null when free.
     *
     * @return array{sessionId:int, conversationId:?int, sessionName:string, endsAt:?string}|null
     */
    public function activeLockFor(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $now  = new DateTimeImmutable('now');
        $rows = $this->sessions->examCandidatesForStudent($userId);

        foreach ($rows as $row) {
            $session = Session::fromRow($row);

            // Guard the type even though SQL already filters it, then defer
            // the running/expired decision to the domain.
            if ($session->type() !== SessionType::Exam) {
                continue;
            }
            if (!$session->isActive($now)) {
                continue;
            }

            $sessionId      = (int) $session->id();
            $conversationId = $this->conversations->findIdByUserAndSession($userId, $sessionId);

            return [
                'sessionId'      => $sessionId,
                'conversationId' => $conversationId,
                'sessionName'    => $session->name(),
                'endsAt'         => $session->endsAt()?->format(DateTimeInterface::ATOM),
            ];
        }

        return null;
    }
}
