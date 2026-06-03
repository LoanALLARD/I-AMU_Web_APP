<?php

declare(strict_types=1);

namespace Services;

use DateTimeImmutable;
use Domain\Session;
use Domain\SessionException;
use Domain\SessionStatus;
use InvalidArgumentException;
use Models\AiRepository;
use Models\ConversationRepository;
use Models\EnrollmentRepository;
use Models\ResourceRepository;
use Models\SessionRepository;
use PDO;

/**
 * Application logic for the Session feature. Replaces the nine hexagonal
 * use-case services with one class: it wires the repositories, hydrates
 * Domain\Session for the rules, and returns plain arrays for the views.
 */
class SessionService
{
    private SessionRepository $sessions;
    private ResourceRepository $resources;
    private AiRepository $models;
    private EnrollmentRepository $enrollments;
    private ConversationRepository $conversations;

    public function __construct(PDO $pdo)
    {
        $this->sessions      = new SessionRepository($pdo);
        $this->resources     = new ResourceRepository($pdo);
        $this->models        = new AiRepository($pdo);
        $this->enrollments   = new EnrollmentRepository($pdo);
        $this->conversations = new ConversationRepository($pdo);
    }

    public function find(int $id): ?Session
    {
        $row = $this->sessions->findById($id);

        return $row === null ? null : Session::fromRow($row);
    }

    public function resourceBelongsTo(int $resourceId, int $teacherId): bool
    {
        $resource = $this->resources->findById($resourceId);

        return $resource !== null && (int) $resource['owner_id'] === $teacherId;
    }

    // ----------------------------------------------------------------
    // Reads for views
    // ----------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTeacher(int $teacherId): array
    {
        $now = $this->now();

        return array_map(
            fn (array $row): array => $this->listRow(Session::fromRow($row), $now),
            $this->sessions->findAllByTeacher($teacherId)
        );
    }

    /**
     * @return array{models: list<array<string, mixed>>, resources: list<array<string, mixed>>, previewCode: string, previewCodeFormatted: string}
     */
    public function createFormData(int $teacherId): array
    {
        $code = $this->sessions->generateUniqueAccessCode();

        return [
            'models'               => $this->modelOptions(),
            'resources'            => $this->resources->findAllByOwner($teacherId),
            'previewCode'          => $code,
            'previewCodeFormatted' => Session::formatAccessCode($code),
        ];
    }

    /**
     * @return array{session: Session, models: list<array<string, mixed>>, resources: list<array<string, mixed>>, authorizedModelIds: list<int>, previewCode: string, previewCodeFormatted: string}
     */
    public function editFormData(Session $session, int $teacherId): array
    {
        return [
            'session'              => $session,
            'models'               => $this->modelOptions(),
            'resources'            => $this->resources->findAllByOwner($teacherId),
            'authorizedModelIds'   => $this->sessions->authorizedModelIdsOf((int) $session->id()),
            'previewCode'          => $session->accessCode(),
            'previewCodeFormatted' => $session->accessCodeFormatted(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Session $session): array
    {
        $now      = $this->now();
        $modelIds = $this->sessions->authorizedModelIdsOf((int) $session->id());
        $models   = array_map(
            static fn (array $m): array => [
                'name'    => (string) $m['name'],
                'version' => $m['version'] ?? null,
            ],
            $this->models->findByIds($modelIds)
        );

        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);

        return [
            'id'                 => (int) $session->id(),
            'name'               => $session->name(),
            'typeLabel'          => $session->type()->label(),
            'typeClass'          => $session->type()->badgeClass(),
            'statusLabel'        => $computed->label(),
            'statusClass'        => $computed->badgeClass(),
            'accessCode'         => $session->accessCodeFormatted(),
            'startsAtFormatted'  => $session->startsAt()?->format('d/m/Y H:i'),
            'endsAtFormatted'    => $session->endsAt()?->format('d/m/Y H:i'),
            'closedAtFormatted'  => $session->closedAt()?->format('d/m/Y H:i'),
            'prePromptOverride'  => $session->prePromptOverride(),
            'postPromptOverride' => $session->postPromptOverride(),
            'instructions'       => $session->instructions(),
            'maxInputSize'       => $session->maxInputSize(),
            'authorizedModels'   => $models,
            'canEdit'            => $actions['can_edit'],
            'canStart'           => $actions['can_start'],
            'canEnd'             => $actions['can_end'],
            'canCancel'          => $actions['can_cancel'],
        ];
    }

    // ----------------------------------------------------------------
    // Mutations
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data validated form data
     */
    public function create(array $data, int $teacherId): Session
    {
        if ($data['modelIds'] === []) {
            throw new InvalidArgumentException('Une session doit autoriser au moins un modèle.');
        }
        if ((int) $data['resourceId'] <= 0) {
            throw new InvalidArgumentException('Une ressource est obligatoire.');
        }

        /** @var ?DateTimeImmutable $startsAt */
        $startsAt = $data['startsAt'];
        $endsAt   = $this->deriveEndsAt($startsAt, (int) $data['durationMinutes']);
        $status   = $startsAt === null ? SessionStatus::Draft : SessionStatus::Scheduled;

        $session = new Session(
            null,
            (int) $data['resourceId'],
            $teacherId,
            (string) $data['name'],
            $data['type'],
            $status,
            $this->resolveAccessCode($data['accessCode'] ?? null),
            $startsAt,
            $endsAt,
            null,
            $data['prePrompt'],
            $data['postPrompt'],
            $data['instructions'],
            $data['maxInputSize'],
        );

        $id = $this->sessions->insert($session->toRow());
        $session->assignId($id);
        $this->sessions->setAuthorizedModels($id, $data['modelIds']);

        return $session;
    }

    /**
     * @param array<string, mixed> $data validated form data
     */
    public function update(int $id, array $data): void
    {
        if ($data['modelIds'] === []) {
            throw new InvalidArgumentException('Une session doit autoriser au moins un modèle.');
        }

        $session = $this->find($id) ?? throw SessionException::notFound($id);
        $now     = $this->now();

        /** @var ?DateTimeImmutable $startsAt */
        $startsAt = $data['startsAt'];
        $endsAt   = $this->deriveEndsAt($startsAt, (int) $data['durationMinutes']);

        $session->rename((string) $data['name'], $now);
        $session->reschedule($startsAt, $endsAt, $now);
        $session->reconfigure($data['prePrompt'], $data['postPrompt'], $data['instructions'], $data['maxInputSize'], $now);

        $this->sessions->update($id, $session->toRow());
        $this->sessions->setAuthorizedModels($id, $data['modelIds']);
    }

    public function start(int $id): void
    {
        $session = $this->find($id) ?? throw SessionException::notFound($id);
        $session->start($this->now());
        $this->sessions->update($id, $session->toRow());
    }

    public function end(int $id): void
    {
        $session = $this->find($id) ?? throw SessionException::notFound($id);
        $session->end($this->now());
        $this->sessions->update($id, $session->toRow());
    }

    public function cancel(int $id): void
    {
        $session = $this->find($id) ?? throw SessionException::notFound($id);
        $session->cancel($this->now());
        $this->sessions->update($id, $session->toRow());
    }

    /**
     * Idempotent student join: enrols (once) and reuses / creates the
     * session-bound conversation.
     *
     * @return array{conversationId: int, alreadyJoined: bool, sessionName: string}
     */
    public function join(string $rawCode, int $studentUserId): array
    {
        if ($studentUserId <= 0) {
            throw new InvalidArgumentException('Utilisateur invalide.');
        }

        $code = Session::normalizeAccessCode($rawCode);
        $row  = $this->sessions->findByAccessCode($code);
        if ($row === null) {
            throw SessionException::notFoundByCode($code);
        }

        $session  = Session::fromRow($row);
        $computed = $session->computedStatus($this->now());

        if ($computed === SessionStatus::Cancelled) {
            throw SessionException::notAvailable('Cette session a été annulée.');
        }
        if ($computed !== SessionStatus::Active) {
            throw SessionException::notAvailable(match ($computed) {
                SessionStatus::Ended     => 'Cette session est terminée.',
                SessionStatus::Scheduled => "Cette session n'a pas encore commencé.",
                default                  => "Cette session n'est pas encore ouverte.",
            });
        }

        $sessionId     = (int) $session->id();
        $alreadyJoined = $this->enrollments->exists($studentUserId, $sessionId);
        if (!$alreadyJoined) {
            $this->enrollments->enroll($studentUserId, $sessionId);
        }

        $conversationId = $this->conversations->findIdByUserAndSession($studentUserId, $sessionId);
        if ($conversationId === null) {
            $created        = $this->conversations->newConversation($studentUserId, $sessionId, 'SESSION - ' . $session->accessCodeFormatted());
            $conversationId = $created !== null
                ? (int) $created['id']
                : (int) ($this->conversations->findIdByUserAndSession($studentUserId, $sessionId) ?? 0);
        }

        return [
            'conversationId' => $conversationId,
            'alreadyJoined'  => $alreadyJoined,
            'sessionName'    => $session->name(),
        ];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    private function deriveEndsAt(?DateTimeImmutable $startsAt, int $durationMinutes): ?DateTimeImmutable
    {
        if ($startsAt !== null && $durationMinutes > 0) {
            return $startsAt->modify('+' . $durationMinutes . ' minutes');
        }

        return null;
    }

    private function resolveAccessCode(?string $proposed): string
    {
        if ($proposed !== null && $proposed !== '') {
            $normalized = Session::normalizeAccessCode($proposed);
            if (preg_match('/^[A-Z0-9]{6}$/', $normalized) === 1 && !$this->sessions->accessCodeExists($normalized)) {
                return $normalized;
            }
        }

        return $this->sessions->generateUniqueAccessCode();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function modelOptions(): array
    {
        return array_map(
            static fn (array $m): array => [
                'id'            => (int) $m['id'],
                'name'          => (string) $m['name'],
                'version'       => $m['version'] ?? null,
                'contextWindow' => isset($m['context_window']) && $m['context_window'] !== null ? (int) $m['context_window'] : null,
            ],
            $this->models->findAllActive()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Session $session, DateTimeImmutable $now): array
    {
        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);

        return [
            'id'                => (int) $session->id(),
            'name'              => $session->name(),
            'typeLabel'         => $session->type()->label(),
            'typeClass'         => $session->type()->badgeClass(),
            'statusLabel'       => $computed->label(),
            'statusClass'       => $computed->badgeClass(),
            'accessCode'        => $session->accessCodeFormatted(),
            'startsAtFormatted' => $session->startsAt()?->format('d/m/Y H:i'),
            'endsAtFormatted'   => $session->endsAt()?->format('d/m/Y H:i'),
            'canEdit'           => $actions['can_edit'],
            'canStart'          => $actions['can_start'],
            'canEnd'            => $actions['can_end'],
            'canCancel'         => $actions['can_cancel'],
        ];
    }
}
