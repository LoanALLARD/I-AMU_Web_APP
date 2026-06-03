<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Ports\ClockInterface;
use App\Application\Ports\ConversationRepositoryInterface;
use App\Domain\Repositories\SessionRepositoryInterface;
use App\Domain\ValueObjects\SessionStatus;
use Core\Controller;

/**
 * Renders the authenticated chat home (the conversation shell). The
 * actual LLM round-trip is handled by {@see LLMController} on POST /chat.
 *
 * When a conversation id is supplied (GET /chat/{id}), the shell loads
 * that conversation — provided it belongs to the current user — so the
 * topbar shows its name (e.g. "SESSION - N80-RF1"). If the conversation
 * is bound to a session that has ended or been cancelled, the composer
 * is locked (read-only): the linked session is over.
 */
final class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly SessionRepositoryInterface $sessions,
        private readonly ClockInterface $clock,
    ) {
    }

    public function index(?string $conversationId = null): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $conversation  = null;
        $sessionClosed = false;
        $closedReason  = '';

        if ($conversationId !== null) {
            $view = $this->conversations->findOwnedById((int) $conversationId, $user['id']);
            if ($view === null) {
                $this->flash('error', "Conversation introuvable.");
                $this->redirect('/chat');
            }
            $conversation = [
                'id'        => $view->id,
                'name'      => $view->name,
                'sessionId' => $view->sessionId,
            ];

            // A session-bound conversation goes read-only once its session
            // is no longer active (ended or cancelled).
            if ($view->sessionId !== null) {
                $session = $this->sessions->findById($view->sessionId);
                if ($session !== null) {
                    $status = $session->computedStatus($this->clock->now());
                    if ($status === SessionStatus::Ended) {
                        $sessionClosed = true;
                        $closedReason  = "Cette session est terminée.";
                    } elseif ($status === SessionStatus::Cancelled) {
                        $sessionClosed = true;
                        $closedReason  = "Cette session a été annulée.";
                    }
                }
            }
        }

        $conversations = array_map(
            static fn ($v) => ['id' => $v->id, 'name' => $v->name],
            $this->conversations->findRecentByUser($user['id'])
        );

        $this->render('pages/homeView', [
            'user'          => $user,
            'page'          => 'chat',
            'conversation'  => $conversation,
            'conversations' => $conversations,
            'sessionClosed' => $sessionClosed,
            'closedReason'  => $closedReason,
        ], 'chat');
    }
}
