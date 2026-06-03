<?php

declare(strict_types=1);

namespace Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Session aggregate: a teacher-run, code-accessed LLM session.
 *
 * Holds its own lifecycle rules (start / end / cancel), derived status and
 * presentation helpers, so controllers and services never reimplement the
 * state machine. Built from a DB row via {@see fromRow()} and serialised back
 * for persistence via {@see toRow()} — the Model layer only handles arrays.
 */
class Session
{
    public function __construct(
        private ?int $id,
        private readonly int $resourceId,
        private readonly ?int $teacherId,
        private string $name,
        private SessionType $type,
        private SessionStatus $status,
        private ?string $accessCode,
        private ?DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $endsAt,
        private ?DateTimeImmutable $closedAt,
        private ?string $prePromptOverride,
        private ?string $postPromptOverride,
        private ?string $instructions,
        private ?int $maxInputSize,
    ) {
    }

    // ----------------------------------------------------------------
    // Hydration / serialisation
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $date = static function (mixed $v): ?DateTimeImmutable {
            return ($v === null || $v === '') ? null : new DateTimeImmutable((string) $v);
        };

        return new self(
            id:                 isset($row['id']) && $row['id'] !== null ? (int) $row['id'] : null,
            resourceId:         (int) $row['resource_id'],
            teacherId:          isset($row['teacher_id']) && $row['teacher_id'] !== null ? (int) $row['teacher_id'] : null,
            name:               (string) $row['name'],
            type:               SessionType::from((string) $row['type']),
            status:             SessionStatus::from((string) $row['status']),
            accessCode:         isset($row['access_code']) && $row['access_code'] !== null && $row['access_code'] !== '' ? (string) $row['access_code'] : null,
            startsAt:           $date($row['starts_at'] ?? null),
            endsAt:             $date($row['ends_at'] ?? null),
            closedAt:           $date($row['closed_at'] ?? null),
            prePromptOverride:  isset($row['pre_prompt_override']) && $row['pre_prompt_override'] !== null ? (string) $row['pre_prompt_override'] : null,
            postPromptOverride: isset($row['post_prompt_override']) && $row['post_prompt_override'] !== null ? (string) $row['post_prompt_override'] : null,
            instructions:       isset($row['instructions']) && $row['instructions'] !== null ? (string) $row['instructions'] : null,
            maxInputSize:       isset($row['max_input_size']) && $row['max_input_size'] !== null ? (int) $row['max_input_size'] : null,
        );
    }

    /**
     * Flat scalar map for persistence (INSERT / UPDATE bindings).
     *
     * @return array<string, scalar|null>
     */
    public function toRow(): array
    {
        return [
            'resource_id'          => $this->resourceId,
            'name'                 => $this->name,
            'type'                 => $this->type->value,
            'status'               => $this->status->value,
            'access_code'          => $this->accessCode,
            'starts_at'            => $this->startsAt?->format('c'),
            'ends_at'              => $this->endsAt?->format('c'),
            'closed_at'            => $this->closedAt?->format('c'),
            'pre_prompt_override'  => $this->prePromptOverride,
            'post_prompt_override' => $this->postPromptOverride,
            'instructions'         => $this->instructions,
            'max_input_size'       => $this->maxInputSize,
        ];
    }

    // ----------------------------------------------------------------
    // Accessors
    // ----------------------------------------------------------------

    public function id(): ?int { return $this->id; }
    public function resourceId(): int { return $this->resourceId; }
    public function teacherId(): ?int { return $this->teacherId; }
    public function name(): string { return $this->name; }
    public function type(): SessionType { return $this->type; }
    public function status(): SessionStatus { return $this->status; }
    public function accessCode(): ?string { return $this->accessCode; }
    public function accessCodeFormatted(): ?string { return $this->accessCode === null ? null : self::formatAccessCode($this->accessCode); }
    public function startsAt(): ?DateTimeImmutable { return $this->startsAt; }
    public function endsAt(): ?DateTimeImmutable { return $this->endsAt; }
    public function closedAt(): ?DateTimeImmutable { return $this->closedAt; }
    public function prePromptOverride(): ?string { return $this->prePromptOverride; }
    public function postPromptOverride(): ?string { return $this->postPromptOverride; }
    public function instructions(): ?string { return $this->instructions; }
    public function maxInputSize(): ?int { return $this->maxInputSize; }

    public function assignId(int $id): void
    {
        if ($this->id !== null && $this->id !== $id) {
            throw new LogicException('Session id already assigned.');
        }
        $this->id = $id;
    }

    /**
     * Sets the access code generated by the database trigger (after the
     * session is persisted with a SCHEDULED/ACTIVE status). May be null
     * while the session is still a draft.
     */
    public function assignAccessCode(?string $code): void
    {
        $this->accessCode = $code;
    }

    // ----------------------------------------------------------------
    // Mutators (only while editable)
    // ----------------------------------------------------------------

    public function rename(string $name, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);
        $this->name = $name;
    }

    public function reschedule(?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('La fin doit être postérieure au démarrage.');
        }
        $this->startsAt = $startsAt;
        $this->endsAt   = $endsAt;
        $this->status   = $startsAt === null ? SessionStatus::Draft : SessionStatus::Scheduled;
    }

    public function reconfigure(?string $prePrompt, ?string $postPrompt, ?string $instructions, ?int $maxInputSize, DateTimeImmutable $now): void
    {
        $this->guardEditable($now);
        if ($maxInputSize !== null && $maxInputSize <= 0) {
            throw new InvalidArgumentException('La limite par prompt doit être positive.');
        }
        $this->prePromptOverride  = $prePrompt;
        $this->postPromptOverride = $postPrompt;
        $this->instructions       = $instructions;
        $this->maxInputSize       = $maxInputSize;
    }

    // ----------------------------------------------------------------
    // Lifecycle transitions
    // ----------------------------------------------------------------

    public function start(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Cancelled) {
            throw SessionException::cancelled();
        }
        if ($this->status === SessionStatus::Ended) {
            throw SessionException::alreadyEnded();
        }
        if ($this->status === SessionStatus::Active) {
            throw SessionException::alreadyStarted();
        }
        // Enforce the DB invariant closed_at >= starts_at by anchoring an
        // unset / future start to now.
        if ($this->startsAt === null || $this->startsAt > $now) {
            $this->startsAt = $now;
        }
        $this->status = SessionStatus::Active;
    }

    public function end(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Cancelled) {
            throw SessionException::cancelled();
        }
        if ($this->status === SessionStatus::Ended) {
            throw SessionException::alreadyEnded();
        }
        if ($this->startsAt === null || $this->startsAt > $now) {
            $this->startsAt = $now;
        }
        $endsAt = $now;
        if ($endsAt <= $this->startsAt) {
            $endsAt = $this->startsAt->modify('+1 second');
        }
        $this->endsAt   = $endsAt;
        $this->closedAt = $now;
        $this->status   = SessionStatus::Ended;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if ($this->status === SessionStatus::Ended) {
            throw SessionException::alreadyEnded();
        }
        if ($this->status === SessionStatus::Cancelled) {
            throw SessionException::cancelled();
        }
        if ($this->startsAt !== null && $this->startsAt > $now) {
            $this->startsAt = $now;
        }
        $this->closedAt = $now;
        $this->status   = SessionStatus::Cancelled;
    }

    // ----------------------------------------------------------------
    // Derived state
    // ----------------------------------------------------------------

    public function canBeModified(DateTimeImmutable $now): bool
    {
        $editableStatus = $this->status === SessionStatus::Draft || $this->status === SessionStatus::Scheduled;
        return $editableStatus && ($this->startsAt === null || $this->startsAt > $now);
    }

    /**
     * Status as it should be displayed at $now: a SCHEDULED session whose
     * start/end has passed shows as ACTIVE / ENDED without a stored write.
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
     * @return array{can_edit: bool, can_start: bool, can_end: bool, can_cancel: bool}
     */
    public function availableActions(DateTimeImmutable $now): array
    {
        $computed = $this->computedStatus($now);
        return [
            'can_edit'   => $this->canBeModified($now),
            'can_start'  => $computed === SessionStatus::Draft || $computed === SessionStatus::Scheduled,
            'can_end'    => $computed === SessionStatus::Active,
            'can_cancel' => !$computed->isTerminal(),
        ];
    }

    public function isActive(DateTimeImmutable $now): bool
    {
        return $this->computedStatus($now) === SessionStatus::Active;
    }

    private function guardEditable(DateTimeImmutable $now): void
    {
        if (!$this->canBeModified($now)) {
            throw SessionException::notEditable();
        }
    }

    // ----------------------------------------------------------------
    // Access code helpers (replaces the former AccessCode value object)
    // ----------------------------------------------------------------

    /** "ABC123" -> "ABC-123" */
    public static function formatAccessCode(string $code): string
    {
        return strlen($code) === 6 ? substr($code, 0, 3) . '-' . substr($code, 3) : $code;
    }

    /** Strips separators / spaces and upper-cases user input. */
    public static function normalizeAccessCode(string $raw): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
    }
}
