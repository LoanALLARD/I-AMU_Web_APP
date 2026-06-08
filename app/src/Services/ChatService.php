<?php

declare(strict_types=1);

namespace Services;

use DateTimeImmutable;
use Domain\Session;
use Domain\SessionStatus;
use Models\ConversationRepository;
use Models\EnrollmentRepository;
use Models\InteractionRepository;
use Models\SessionRepository;
use PDO;
use RuntimeException;

/**
 * Chat environments. A conversation is either bound to a session (the
 * "session" environment, where the sidebar lists that session's
 * conversations) or free (the "libre" environment). This service resolves
 * the current environment for the chat shell and creates new conversations
 * within it.
 */
class ChatService
{
    private ConversationRepository $conversations;
    private SessionRepository $sessions;
    private InteractionRepository $interactions;
    private EnrollmentRepository $enrollments;

    public function __construct(PDO $pdo)
    {
        $this->conversations = new ConversationRepository($pdo);
        $this->sessions      = new SessionRepository($pdo);
        $this->interactions  = new InteractionRepository($pdo);
        $this->enrollments   = new EnrollmentRepository($pdo);
    }

    /**
     * Lightweight check used by the chat page's live poller: is the user's
     * session conversation now read-only (session ended/cancelled, or the
     * student deactivated by the teacher)?
     *
     * @return array{closed: bool, reason: string}
     */
    public function sessionStatusFor(int $userId, int $conversationId): array
    {
        $row = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
        if ($row === null || $row['session_id'] === null) {
            return ['closed' => false, 'reason' => ''];
        }
        $sessionId = (int) $row['session_id'];

        $sessionRow = $this->sessions->findById($sessionId);
        if ($sessionRow !== null) {
            $status = Session::fromRow($sessionRow)->computedStatus(new DateTimeImmutable('now'));
            if ($status === SessionStatus::Ended) {
                return ['closed' => true, 'reason' => 'Cette session est terminée.'];
            }
            if ($status === SessionStatus::Cancelled) {
                return ['closed' => true, 'reason' => 'Cette session a été annulée.'];
            }
        }

        if (
            $this->enrollments->exists($userId, $sessionId)
            && !$this->enrollments->isActive($userId, $sessionId)
        ) {
            return ['closed' => true, 'reason' => "Vous avez été retiré de cette session par l'enseignant."];
        }

        return ['closed' => false, 'reason' => ''];
    }

    /**
     * Everything the chat shell needs for a given (user, conversation):
     * the open conversation, the environment-filtered conversation list,
     * and whether the linked session is closed.
     *
     * `notFound` is true when a conversation id was given but does not
     * belong to the user (the controller redirects to /chat). `$archived`
     * lists the archived conversations of the environment instead of the
     * active ones (the open conversation is unaffected by the flag).
     *
     * @return array<string, mixed>
     */
    public function environment(int $userId, ?int $conversationId, bool $archived = false): array
    {
        $conversation = null;
        $sessionId    = null;

        if ($conversationId !== null) {
            $row = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
            if ($row === null) {
                return ['notFound' => true];
            }
            $sessionId    = $row['session_id'] !== null ? (int) $row['session_id'] : null;
            $conversation = [
                'id'        => (int) $row['id'],
                'name'      => (string) $row['name'],
                'sessionId' => $sessionId,
            ];
        }

        $sessionClosed = false;
        $closedReason  = '';

        if ($sessionId !== null) {
            $envMode  = 'session';
            $envLabel = 'Session';
            $row      = $this->sessions->findById($sessionId);
            if ($row !== null) {
                $session  = Session::fromRow($row);
                $code     = $session->accessCodeFormatted();
                $envLabel = $code !== null ? 'Session ' . $code : 'Session';
                $status   = $session->computedStatus(new DateTimeImmutable('now'));
                if ($status === SessionStatus::Ended) {
                    $sessionClosed = true;
                    $closedReason  = 'Cette session est terminée.';
                } elseif ($status === SessionStatus::Cancelled) {
                    $sessionClosed = true;
                    $closedReason  = 'Cette session a été annulée.';
                }
            }

            // A student deactivated by the teacher sees the session as closed
            // (read-only) on their next load — the soft "disconnect".
            if (
                $this->enrollments->exists($userId, $sessionId)
                && !$this->enrollments->isActive($userId, $sessionId)
            ) {
                $sessionClosed = true;
                $closedReason  = "Vous avez été retiré de cette session par l'enseignant.";
            }

            $rows = $this->conversations->listByUserAndSession($userId, $sessionId, $archived);
        } else {
            $envMode  = 'libre';
            $envLabel = 'Chat libre';
            $rows     = $this->conversations->listFreeByUser($userId, $archived);
        }

        // Past prompt/response turns of the open conversation, so the chat
        // re-displays its history instead of an empty thread on reload.
        $messages = [];
        if ($conversation !== null) {
            $messages = array_map(
                static fn (array $m): array => [
                    'prompt'   => (string) $m['prompt'],
                    'response' => (string) $m['response'],
                    'model'    => (string) ($m['model_name'] ?? ''),
                ],
             $this->interactions->listByConversation((int) $conversation['id'])
            );
        }

        return [
            'notFound'      => false,
            'conversation'  => $conversation,
            'messages'      => $messages,
            'conversations' => array_map(
                static fn (array $c): array => [
                    'id'          => (int) $c['id'],
                    'name'        => (string) $c['name'],
                    'promptCount' => (int) ($c['prompt_count'] ?? 0),
                ],
                $rows
            ),
            'sessionClosed' => $sessionClosed,
            'closedReason'  => $closedReason,
            'env'           => [
                'mode'      => $envMode,
                'sessionId' => $sessionId,
                'label'     => $envLabel,
            ],
        ];
    }

    /**
     * Creates a new conversation in a session ("SESSION - CODE #N"). The
     * session must be active.
     */
    public function newSessionConversation(int $userId, int $sessionId, int $modelId): int
    {
        $row = $this->sessions->findById($sessionId);
        if ($row === null) {
            throw new RuntimeException('Session introuvable.');
        }

        $session = Session::fromRow($row);
        if ($session->computedStatus(new DateTimeImmutable('now')) !== SessionStatus::Active) {
            throw new RuntimeException("Cette session n'est pas active.");
        }

        $code = $session->accessCodeFormatted() ?? ('S' . $sessionId);
        $n    = $this->conversations->countByUserAndSession($userId, $sessionId) + 1;

        $conversation = $this->conversations->newConversation($userId, $modelId, $sessionId, 'SESSION - ' . $code . ' #' . $n);
        return (int) $conversation['id'];
    }

    /**
     * Creates a new free-mode conversation ("Conversation #N").
     */
    public function newFreeConversation(int $userId, int $modelId): int
    {
        $n = count($this->conversations->listFreeByUser($userId)) + 1;

        $conversation = $this->conversations->newConversation($userId, $modelId, null, 'Conversation #' . $n);
        return (int) $conversation['id'];
    }

    /**
     * Renames a free-mode conversation the user owns. Session conversations
     * keep their generated "SESSION - CODE #N" name and cannot be renamed.
     */
    public function rename(int $userId, int $conversationId, string $name): void
    {
        $row = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
        if ($row === null) {
            throw new RuntimeException('Conversation introuvable.');
        }
        if ($row['session_id'] !== null) {
            throw new RuntimeException('Une conversation de session ne peut pas être renommée.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Le nom ne peut pas être vide.');
        }
        if (mb_strlen($name) > 120) {
            $name = mb_substr($name, 0, 120);
        }

        $this->conversations->rename($userId, $conversationId, $name);
    }

    /**
     * Archives a free-mode conversation the user owns and returns the id of
     * the most recent remaining free conversation (null when none), so the
     * chat shell can land somewhere sensible. Session conversations are
     * managed by the session lifecycle and cannot be archived.
     *
     * @return array{next: int|null}
     */
    public function archive(int $userId, int $conversationId): array
    {
        $row = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
        if ($row === null) {
            throw new RuntimeException('Conversation introuvable.');
        }
        if ($row['session_id'] !== null) {
            throw new RuntimeException('Une conversation de session ne peut pas être archivée.');
        }

        $this->conversations->archive($userId, $conversationId);

        $remaining = $this->conversations->listFreeByUser($userId);
        $next      = $remaining !== [] ? (int) $remaining[0]['id'] : null;

        return ['next' => $next];
    }

    /**
     * Restores an archived conversation the user owns.
     */
    public function unarchive(int $userId, int $conversationId): void
    {
        $row = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
        if ($row === null) {
            throw new RuntimeException('Conversation introuvable.');
        }

        $this->conversations->unarchive($userId, $conversationId);
    }
}
