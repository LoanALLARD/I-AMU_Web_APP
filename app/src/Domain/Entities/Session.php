<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\SessionAlreadyEndedException;
use App\Domain\Exceptions\SessionAlreadyStartedException;
use App\Domain\Exceptions\SessionCancelledException;
use App\Domain\Exceptions\SessionNotEditableException;
use App\Domain\ValueObjects\AccessCode;
use App\Domain\ValueObjects\SessionStatus;
use App\Domain\ValueObjects\SessionType;
use DateTimeImmutable;

/**
 * Session aggregate root.
 *
 * Holds every invariant a session must enforce: lifecycle transitions, edit
 * gating against the scheduled start, the derivation of the displayed status
 * from clock + stored status. The aggregate is persistence-agnostic.
 *
 * `teacherId` is NOT a column on the `sessions` table — it is derived from
 * `resources.owner_id` and hydrated by the repository via a JOIN. The
 * entity exposes it as a read-only accessor so callers (ownership guards,
 * view-models) don't have to fetch the resource themselves.
 */
final class Session
{
    /**
     * @param int|null      $id            Null for a brand-new session, set after `save()`.
     * @param int           $resourceId    FK to resources.id — NOT NULL per schema.
     * @param int|null      $teacherId     Derived from resources.owner_id; null when the
     *                                     repository did not load it (rare: pre-save).
     * @param int|null      $maxInputSize  Per-prompt char/token ceiling. Null = no cap.
     */
    public function __construct(
        private ?int $id,
        private readonly int $resourceId,
        private readonly ?int $teacherId,
        private string $name,
        private SessionType $type,
        private SessionStatus $status,
        private readonly AccessCode $accessCode,
        private ?DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $endsAt,
        private ?DateTimeImmutable $closedAt,
        private ?string $prePromptOverride,
        private ?string $postPromptOverride,
        private ?string $instructions,
        private ?int $maxInputSize,
    ) {
    }

    /**
     * Repositories call this after persisting a brand-new session so the id
     * assigned by the DB is reflected on the entity. Idempotent.
     */
    public function assignId(int $id): void
    {
        if ($this->id !== null && $this->id !== $id) {
            throw new \LogicException("Cannot reassign session id (was {$this->id}, got {$id}).");
        }
        $this->id = $id;
    }

    // ----------------------------------------------------------------
    // Read accessors
    // ----------------------------------------------------------------

    public function id(): ?int                            { return $this->id; }
    public function resourceId(): int                     { return $this->resourceId; }
    public function teacherId(): ?int                     { return $this->teacherId; }
    public function name(): string                        { return $this->name; }
    public function type(): SessionType                   { return $this->type; }
    public function status(): SessionStatus               { return $this->status; }
    public function accessCode(): AccessCode              { return $this->accessCode; }
    public function startsAt(): ?DateTimeImmutable        { return $this->startsAt; }
    public function endsAt(): ?DateTimeImmutable          { return $this->endsAt; }
    public function closedAt(): ?DateTimeImmutable        { return $this->closedAt; }
    public function prePromptOverride(): ?string          { return $this->prePromptOverride; }
    public function postPromptOverride(): ?string         { return $this->postPromptOverride; }
    public function instructions(): ?string               { return $this->instructions; }
    public function maxInputSize(): ?int                  { return $this->maxInputSize; }

    // ----------------------------------------------------------------
    // Lifecycle mutators
    // ----------------------------------------------------------------

    public function rename(string $name, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);
        $this->name = $name;
    }

    public function reschedule(
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        DateTimeImmutable $now
    ): void {
        $this->guardEditable($now);

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new \InvalidArgumentException("ends_at must be strictly after starts_at.");
        }

        $this->startsAt = $startsAt;
        $this->endsAt   = $endsAt;

        $this->status = $startsAt === null
            ? SessionStatus::Draft
            : SessionStatus::Scheduled;
    }

    public function reconfigure(
        ?string $prePromptOverride,
        ?string $postPromptOverride,
        ?string $instructions,
        ?int $maxInputSize,
        DateTimeImmutable $now
    ): void {
        $this->guardEditable($now);

        if ($maxInputSize !== null && $maxInputSize <= 0) {
            throw new \InvalidArgumentException("max_input_size must be strictly positive or null.");
        }

        $this->prePromptOverride  = $prePromptOverride;
        $this->postPromptOverride = $postPromptOverride;
        $this->instructions       = $instructions;
        $this->maxInputSize       = $maxInputSize;
    }

    /**
     * Manual start: flip to ACTIVE.
     *
     * Re-anchors `starts_at` to NOW when it is missing or still in the
     * future — the schema (`ck_sessions_closed_at`) requires
     * `closed_at >= starts_at`, and the teacher's intent when clicking
     * "Démarrer" before the scheduled time is "start it right now".
     * If `starts_at` is already in the past we keep it as the original
     * planned start.
     */
    public function start(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Cancelled) {
            throw new SessionCancelledException("Une session annulée ne peut être démarrée.");
        }
        if ($this->status === SessionStatus::Ended) {
            throw new SessionAlreadyEndedException("Une session terminée ne peut être redémarrée.");
        }
        if ($this->status === SessionStatus::Active) {
            throw new SessionAlreadyStartedException("Session déjà active.");
        }

        $this->status = SessionStatus::Active;
        if ($this->startsAt === null || $this->startsAt > $now) {
            $this->startsAt = $now;
        }
    }

    /**
     * Manual end: flip to ENDED.
     *
     * Same invariant logic as start(): re-anchor any future timestamp
     * to NOW so the DB-level checks (`ends_at > starts_at`,
     * `closed_at >= starts_at`) always hold.
     */
    public function end(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Cancelled) {
            throw new SessionCancelledException("Une session annulée ne peut être terminée.");
        }
        if ($this->status === SessionStatus::Ended) {
            throw new SessionAlreadyEndedException("Session déjà terminée.");
        }

        // If start() was never called and the session is still SCHEDULED in
        // the future, anchor starts_at now so closed_at >= starts_at holds.
        if ($this->startsAt === null || $this->startsAt > $now) {
            $this->startsAt = $now;
        }

        $this->status = SessionStatus::Ended;

        // ends_at must satisfy ck_sessions_dates: STRICTLY greater than
        // starts_at. We first pull it back from the future if needed,
        // then bump it by 1s if it would collide with starts_at — that
        // happens when the teacher ends a session at the same wall-clock
        // second they started it (or before its planned start, in which
        // case start_at was just re-anchored to NOW above).
        if ($this->endsAt === null || $this->endsAt > $now) {
            $this->endsAt = $now;
        }
        if ($this->endsAt <= $this->startsAt) {
            $this->endsAt = $this->startsAt->modify('+1 second');
        }

        $this->closedAt = $now;
    }

    /**
     * Cancellation: flip to CANCELLED, stamp closed_at.
     *
     * If `starts_at` is in the future, anchor it to NOW so the DB-level
     * check `closed_at >= starts_at` is satisfied even when the
     * cancellation happens before the planned start.
     */
    public function cancel(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Ended) {
            throw new SessionAlreadyEndedException("Une session terminée ne peut être annulée.");
        }
        if ($this->status === SessionStatus::Cancelled) {
            throw new SessionCancelledException("Session déjà annulée.");
        }

        if ($this->startsAt !== null && $this->startsAt > $now) {
            $this->startsAt = $now;
        }

        $this->status   = SessionStatus::Cancelled;
        $this->closedAt = $now;
    }

    // ----------------------------------------------------------------
    // Derived state
    // ----------------------------------------------------------------

    public function canBeModified(DateTimeImmutable $now): bool
    {
        if (!in_array($this->status, [SessionStatus::Draft, SessionStatus::Scheduled], true)) {
            return false;
        }
        if ($this->startsAt === null) {
            return true;
        }
        return $this->startsAt > $now;
    }

    /**
     * Returns the status to display, deriving ACTIVE/ENDED from the clock
     * when the stored status is still SCHEDULED.
     */
    public function computedStatus(DateTimeImmutable $now): SessionStatus
    {
        if ($this->status !== SessionStatus::Scheduled) {
            return $this->status;
        }
        if ($this->endsAt !== null && $now > $this->endsAt) {
            return SessionStatus::Ended;
        }
        if ($this->startsAt !== null && $now >= $this->startsAt) {
            return SessionStatus::Active;
        }
        return SessionStatus::Scheduled;
    }

    /**
     * Flags consumed by the contextual action bar in the views.
     *
     * @return array{can_edit:bool, can_start:bool, can_end:bool, can_cancel:bool}
     */
    public function availableActions(DateTimeImmutable $now): array
    {
        $computed = $this->computedStatus($now);
        return [
            'can_edit'   => $this->canBeModified($now),
            'can_start'  => in_array($computed, [SessionStatus::Draft, SessionStatus::Scheduled], true),
            'can_end'    => $computed === SessionStatus::Active,
            'can_cancel' => !$computed->isTerminal(),
        ];
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        return $this->computedStatus($now) === SessionStatus::Active;
    }

    // ----------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------

    private function guardEditable(DateTimeImmutable $now): void
    {
        if (!$this->canBeModified($now)) {
            throw new SessionNotEditableException(
                "Cette session ne peut plus être modifiée (statut: {$this->status->value})."
            );
        }
    }
}
