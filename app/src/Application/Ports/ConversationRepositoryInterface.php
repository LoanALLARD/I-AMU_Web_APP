<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\ConversationView;

/**
 * Persistence port for conversations.
 *
 * Lives in Application\Ports (not Domain\Repositories) because it returns
 * an Application DTO ({@see ConversationView}); the Domain layer must not
 * depend on Application. Same rationale as ModelReadRepositoryInterface.
 */
interface ConversationRepositoryInterface
{
    /**
     * Returns the id of the conversation a user already has for a session,
     * or null if none exists yet.
     */
    public function findIdByUserAndSession(int $userId, int $sessionId): ?int;

    /**
     * Creates a session-bound conversation and returns its new id.
     */
    public function create(int $userId, int $sessionId, string $name): int;

    /**
     * Loads a conversation only if it belongs to the given user (ownership
     * guard for the chat shell). Returns null otherwise.
     */
    public function findOwnedById(int $conversationId, int $userId): ?ConversationView;

    /**
     * Returns the user's non-archived conversations, most recent first,
     * for the sidebar list.
     *
     * @return list<ConversationView>
     */
    public function findRecentByUser(int $userId): array;
}
