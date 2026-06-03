<?php

declare(strict_types=1);

namespace Services;

use DateTimeImmutable;
use Domain\Session;
use Domain\SessionStatus;
use Models\ConversationRepository;
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

    public function __construct(PDO $pdo)
    {
        $this->conversations = new ConversationRepository($pdo);
        $this->sessions      = new SessionRepository($pdo);
    }

    /**
     * Everything the chat shell needs for a given (user, conversation):
     * the open conversation, the environment-filtered conversation list,
     * and whether the linked session is closed.
     *
     * `notFound` is true when a conversation id was given but does not
     * belong to the user (the controller redirects to /chat).
     *
     * @return array<string, mixed>
     */
    public function environment(int $userId, ?int $conversationId): array
    {
        $conversation = null;
        $sessionId    = null;

        if ($conversationId !== null) {
            $row = $this->conversations->getConversationByUserId($userId, $conversationId);
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
            $rows = $this->conversations->listByUserAndSession($userId, $sessionId);
        } else {
            $envMode  = 'libre';
            $envLabel = 'Chat libre';
            $rows     = $this->conversations->listFreeByUser($userId);
        }

        return [
            'notFound'      => false,
            'conversation'  => $conversation,
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
    public function newSessionConversation(int $userId, int $sessionId): int
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

        return $this->conversations->newConversation($userId, $sessionId, 'SESSION - ' . $code . ' #' . $n);
    }

    /**
     * Creates a new free-mode conversation ("Conversation #N").
     */
    public function newFreeConversation(int $userId): int
    {
        $n = count($this->conversations->listFreeByUser($userId)) + 1;

        return $this->conversations->newConversation($userId, null, 'Conversation #' . $n);
    }
}
