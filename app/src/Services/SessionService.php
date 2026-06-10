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

    /**
     * Returns true if the teacher owns the resource OR has shared access via
     * teacher_resources. Used to gate session creation.
     */
    public function resourceAccessibleByTeacher(int $resourceId, int $teacherId): bool
    {
        return $this->resources->isAccessibleByTeacher($resourceId, $teacherId);
    }

    /**
     * Whether the teacher may VIEW the session read-only: either they own the
     * resource (full rights) or they are attached to it via teacher_resources
     * (responsible). Mutating actions stay owner-only at the controller.
     */
    public function canView(Session $session, int $teacherId): bool
    {
        if ($session->teacherId() === $teacherId) {
            return true;
        }

        return $this->resources->isResourceTeacher($session->resourceId(), $teacherId);
    }

    /**
     * Sessions the teacher supervises read-only (attached via teacher_resources,
     * not owner). Same presentation rows as listForTeacher() so the view renders
     * them identically.
     *
     * @return list<array<string, mixed>>
     */
    public function listSupervisedForTeacher(int $teacherId): array
    {
        $now = $this->now();

        return array_map(
            fn (array $row): array => $this->listRow(Session::fromRow($row), $now),
            $this->sessions->findSupervisedByTeacher($teacherId)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function enrolledStudents(int $sessionId): array
    {
        return $this->sessions->enrolledStudents($sessionId);
    }

    /**
     * Activates or deactivates a student's enrollment in a session (teacher
     * action from the monitor view). Deactivating bars the student from the
     * session chat and from re-joining with the access code.
     */
    public function setStudentActive(int $sessionId, int $studentId, bool $active): void
    {
        $this->enrollments->setActive($studentId, $sessionId, $active);
    }

    /**
     * Research export of a whole session: the session header plus every
     * enrolled student, their conversations and the interactions of each.
     * Identity is included on purpose (no platform-side anonymisation —
     * cf. spec 06). Authorisation is the controller's job.
     *
     * $options filters the output:
     *   - excludeIds: list<int> students to leave out,
     *   - includePrompts / includeResponses: bool (default true).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function exportSessionData(Session $session, array $options = []): array
    {
        $excludeIds       = is_array($options['excludeIds'] ?? null) ? $options['excludeIds'] : [];
        $includePrompts   = ($options['includePrompts']   ?? true) !== false;
        $includeResponses = ($options['includeResponses'] ?? true) !== false;
        $excluded         = array_fill_keys(array_map('intval', $excludeIds), true);

        $sessionId = (int) $session->id();

        $students = [];
        foreach ($this->sessions->exportRows($sessionId) as $r) {
            $sid = (int) $r['student_id'];
            if (isset($excluded[$sid])) {
                continue; // student excluded by the teacher
            }
            if (!isset($students[$sid])) {
                $students[$sid] = [
                    'student_id'     => $sid,
                    'first_name'     => $r['first_name'],
                    'last_name'      => $r['last_name'],
                    'email'          => $r['email'],
                    'student_number' => $r['student_number'],
                    'conversations'  => [],
                ];
            }

            if ($r['conversation_id'] === null) {
                continue; // enrolled student with no conversation yet
            }

            $cid = (int) $r['conversation_id'];
            if (!isset($students[$sid]['conversations'][$cid])) {
                $students[$sid]['conversations'][$cid] = [
                    'id'           => $cid,
                    'name'         => $r['conversation_name'],
                    'created_at'   => $r['conversation_created'],
                    'is_archived'  => (bool) $r['is_archived'],
                    'interactions' => [],
                ];
            }

            if ($r['interaction_id'] !== null) {
                $turn = ['id' => (int) $r['interaction_id']];
                if ($includePrompts) {
                    $turn['prompt'] = $r['prompt'];
                }
                if ($includeResponses) {
                    $turn['response'] = $r['response'];
                }
                $turn['model']         = $r['model_name'];
                $turn['input_tokens']  = $r['input_tokens']  !== null ? (int) $r['input_tokens']  : null;
                $turn['output_tokens'] = $r['output_tokens'] !== null ? (int) $r['output_tokens'] : null;
                $turn['latency']       = $r['latency']       !== null ? (int) $r['latency']       : null;
                $turn['user_feedback'] = $r['user_feedback'] !== null ? (int) $r['user_feedback'] : null;
                $turn['sent_at']       = $r['sent_at'];

                $students[$sid]['conversations'][$cid]['interactions'][] = $turn;
            }
        }

        // Drop the assoc keys used for grouping -> plain lists.
        $studentsList = array_values(array_map(
            static function (array $s): array {
                $s['conversations'] = array_values($s['conversations']);
                return $s;
            },
            $students
        ));

        return [
            'session' => [
                'id'          => $sessionId,
                'name'        => $session->name(),
                'type'        => $session->type()->value,
                'status'      => $session->status()->value,
                'access_code' => $session->accessCode(),
                'starts_at'   => $session->startsAt()?->format('c'),
                'ends_at'     => $session->endsAt()?->format('c'),
            ],
            'exported_at'   => $this->now()->format('c'),
            'filters'       => [
                'excluded_student_ids' => array_values(array_map('intval', $excludeIds)),
                'include_prompts'      => $includePrompts,
                'include_responses'    => $includeResponses,
            ],
            'student_count' => count($studentsList),
            'students'      => $studentsList,
        ];
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
     * @return array{models: list<array<string, mixed>>, resources: list<array<string, mixed>>, previewCode: string, previewCodeFormatted: string, availableFormats: list<string>, authorizedFormats: list<string>}
     */
    public function createFormData(int $teacherId): array
    {
        // No code to preview: the database trigger assigns one only when
        // the session becomes scheduled/active.
        $resourceData = $this->resources->findAllAccessibleByTeacher($teacherId);
        if ($resourceData == null){
            throw new InvalidArgumentException("L'enseignent doit être associé à au minimum une ressource, \n veuillez contacter un administrateur");
        }
        $depId = $resourceData[0]['department_id'];
        return [
            'models'               => $this->modelOptions($depId),
            'resources'            => $resourceData,
            'previewCode'          => '',
            'previewCodeFormatted' => '',
            'availableFormats'     => $this->sessions->listAllFileFormats(),
            // New sessions default to every format authorised (imports enabled).
            'authorizedFormats'    => $this->sessions->listAllFileFormats(),
        ];
    }

    /**
     * @return array{session: Session, models: list<array<string, mixed>>, resources: list<array<string, mixed>>, authorizedModelIds: list<int>, previewCode: string, previewCodeFormatted: string, availableFormats: list<string>, authorizedFormats: list<string>}
     */
    public function editFormData(Session $session, int $teacherId): array
    {
        $resourceData = $this->resources->findAllAccessibleByTeacher($teacherId);
        if ($resourceData == null){
            throw new InvalidArgumentException("L'enseignent doit être associé à au minimum une ressource, \n veuillez contacter un administrateur");
        }
        $depId = $resourceData[0]['department_id'];
        return [
            'session'              => $session,
            'models'               => $this->modelOptions($depId),
            'resources'            => $resourceData,
            'authorizedModelIds'   => $this->sessions->authorizedModelIdsOf((int) $session->id()),
            'previewCode'          => $session->accessCode() ?? '',
            'previewCodeFormatted' => $session->accessCodeFormatted() ?? '',
            'availableFormats'     => $this->sessions->listAllFileFormats(),
            'authorizedFormats'    => $this->sessions->authorizedFormatsOf((int) $session->id()),
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
                'size'    => $m['size'] ?? null,
            ],
            $this->models->findByIds($modelIds)
        );

        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);

        $docTypeMap = ['pdf' => 'PDF', 'md' => 'Markdown', 'txt' => 'TXT'];
        $authorizedFormats = $this->sessions->authorizedFormatsOf((int) $session->id());
        $documentsTypesLabel = implode(', ', array_map(
            static fn (string $t): string => $docTypeMap[$t] ?? strtoupper($t),
            $authorizedFormats
        ));
        $documentsMaxMb = $session->documentsMaxBytes() !== null
            ? (int) ($session->documentsMaxBytes() / 1024 / 1024)
            : 10;
        // What students can import: an exam forces it off; otherwise imports are
        // enabled iff at least one file format is authorised for the session.
        if ($session->type()->value === 'EXAM') {
            $documentsImportLabel = 'Désactivé (examen)';
        } elseif ($authorizedFormats !== []) {
            $documentsImportLabel = 'Autorisé — ' . $documentsTypesLabel . ' · ' . $documentsMaxMb . ' Mo max';
        } else {
            $documentsImportLabel = 'Désactivé';
        }

        return [
            'id'                 => (int) $session->id(),
            'name'               => $session->name(),
            'typeLabel'          => $session->type()->label(),
            'typeClass'          => $session->type()->badgeClass(),
            'statusLabel'        => $computed->label(),
            'statusClass'        => $computed->badgeClass(),
            'accessCode'         => $session->accessCodeFormatted() ?? '',
            'startsAtFormatted'  => $session->startsAt()?->format('d/m/Y H:i'),
            'endsAtFormatted'    => $session->endsAt()?->format('d/m/Y H:i'),
            'closedAtFormatted'  => $session->closedAt()?->format('d/m/Y H:i'),
            'prePromptOverride'  => $session->prePromptOverride(),
            'postPromptOverride' => $session->postPromptOverride(),
            'instructions'       => $session->instructions(),
            'maxInputSize'       => $session->maxInputSize(),
            'maxTokens'          => $session->maxTokens(),
            'documentsImportLabel' => $documentsImportLabel,
            'authorizedModels'   => $models,
            'canEdit'            => $actions['can_edit'],
            'canStart'           => $actions['can_start'],
            'canEnd'             => $actions['can_end'],
            'canCancel'          => $actions['can_cancel'],
            'canMonitor'         => $computed === SessionStatus::Active || $computed === SessionStatus::Ended,
        ];
    }

    /**
     * Aggregate statistics for a session — available once it is running or
     * ended. Returns null otherwise (nothing meaningful to show yet).
     *
     * @return array<string, mixed>|null
     */
    public function statistics(Session $session): ?array
    {
        $now      = $this->now();
        $computed = $session->computedStatus($now);
        if ($computed !== SessionStatus::Active && $computed !== SessionStatus::Ended) {
            return null;
        }

        $sessionId = (int) $session->id();
        $stats     = $this->sessions->statsForSession($sessionId);
        $o         = $stats['overview'];

        $enrolled  = (int) ($o['enrolled'] ?? 0);
        $active    = (int) ($o['active_participants'] ?? 0);
        $input     = (int) ($o['input_tokens'] ?? 0);
        $output    = (int) ($o['output_tokens'] ?? 0);
        $maxTokens = $session->maxTokens();

        // Per-student rows (enrolled-but-inactive students are included).
        $students = [];
        $inactive = 0;
        foreach ($stats['perStudent'] as $r) {
            $prompts = (int) $r['prompts'];
            $tokens  = (int) $r['tokens'];
            if ($prompts === 0) {
                $inactive++;
            }
            $students[] = [
                'name'          => trim(((string) $r['first_name']) . ' ' . ((string) $r['last_name'])),
                'studentNumber' => $r['student_number'] !== null ? (string) $r['student_number'] : null,
                'conversations' => (int) $r['conversations'],
                'prompts'       => $prompts,
                'tokens'        => $tokens,
                'tokensPct'     => $maxTokens !== null && $maxTokens > 0
                    ? min(100, (int) round($tokens / $maxTokens * 100))
                    : null,
                'lastActivity'  => $r['last_activity'] !== null
                    ? (new DateTimeImmutable((string) $r['last_activity']))->format('d/m/Y H:i')
                    : null,
                'feedbackUp'    => (int) $r['feedback_up'],
                'feedbackDown'  => (int) $r['feedback_down'],
                'active'        => $prompts > 0,
            ];
        }

        // Prompts-per-hour bars, scaled to the busiest hour.
        $peak = 0;
        foreach ($stats['activityByHour'] as $b) {
            $peak = max($peak, (int) $b['prompts']);
        }
        $activity = array_map(
            static function (array $b) use ($peak): array {
                $n = (int) $b['prompts'];
                return [
                    'label'     => (new DateTimeImmutable((string) $b['bucket']))->format('d/m H\\h'),
                    'prompts'   => $n,
                    'heightPct' => $peak > 0 ? max(4, (int) round($n / $peak * 100)) : 0,
                ];
            },
            $stats['activityByHour']
        );

        // Satisfaction: 1 = up, -1 = down, 0 = neutral; NULL = not rated (ignored).
        $up = $down = $neutral = 0;
        foreach ($stats['feedback'] as $f) {
            if ($f['feedback'] === null) {
                continue;
            }
            $n = (int) $f['n'];
            match ((int) $f['feedback']) {
                1       => $up = $n,
                -1      => $down = $n,
                default => $neutral = $n,
            };
        }

        $byModel = array_map(
            static fn (array $m): array => [
                'name'    => (string) $m['model_name'],
                'prompts' => (int) $m['prompts'],
                'tokens'  => (int) $m['tokens'],
            ],
            $stats['byModel']
        );

        return [
            'id'            => $sessionId,
            'name'          => $session->name(),
            'statusLabel'   => $computed->label(),
            'statusClass'   => $computed->badgeClass(),
            'isOngoing'     => $computed === SessionStatus::Active,
            'kpi'           => [
                'enrolled'          => $enrolled,
                'active'            => $active,
                'participationRate' => $enrolled > 0 ? (int) round($active / $enrolled * 100) : 0,
                'prompts'           => (int) ($o['prompts'] ?? 0),
                'inputTokens'       => $input,
                'outputTokens'      => $output,
                'totalTokens'       => $input + $output,
                'avgLatency'        => (int) ($o['avg_latency'] ?? 0),
                'medianLatency'     => (int) ($o['median_latency'] ?? 0),
                'avgPromptLen'      => (int) ($o['avg_prompt_len'] ?? 0),
                'avgResponseLen'    => (int) ($o['avg_response_len'] ?? 0),
            ],
            'students'      => $students,
            'inactiveCount' => $inactive,
            'activity'      => $activity,
            'feedback'      => ['up' => $up, 'down' => $down, 'neutral' => $neutral, 'rated' => $up + $down + $neutral],
            'byModel'       => $byModel,
            'maxTokens'     => $maxTokens,
        ];
    }

    /**
     * Read-only supervision view of a session: the enrolled students, each
     * with their (possibly several) conversations, plus the transcript of
     * the selected conversation. Returns null when the session is neither
     * active nor ended (nothing to monitor yet).
     *
     * @return array<string, mixed>|null
     */
    public function monitor(Session $session, int $conversationId = 0): ?array
    {
        $computed = $session->computedStatus($this->now());
        if ($computed !== SessionStatus::Active && $computed !== SessionStatus::Ended) {
            return null;
        }

        $sessionId = (int) $session->id();

        // Group the (student, conversation) rows by student. A student with
        // no conversation still appears, with an empty conversation list.
        $byStudent = [];
        foreach ($this->sessions->monitorStudents($sessionId) as $r) {
            $sid = (int) $r['student_id'];
            if (!isset($byStudent[$sid])) {
                $byStudent[$sid] = [
                    'id'            => $sid,
                    'name'          => trim(((string) $r['first_name']) . ' ' . ((string) $r['last_name'])),
                    'isActive'      => (int) ($r['is_active'] ?? 1) === 1,
                    'conversations' => [],
                    'totalPrompts'  => 0,
                ];
            }
            if ($r['conversation_id'] !== null) {
                $byStudent[$sid]['conversations'][] = [
                    'id'           => (int) $r['conversation_id'],
                    'name'         => (string) $r['conversation_name'],
                    'promptCount'  => (int) $r['prompt_count'],
                    'lastActivity' => $r['last_activity'] !== null
                        ? (new DateTimeImmutable((string) $r['last_activity']))->format('d/m/Y H:i')
                        : null,
                    'lastModel'    => $r['last_model'] !== null && $r['last_model'] !== '' ? (string) $r['last_model'] : null,
                ];
                $byStudent[$sid]['totalPrompts'] += (int) $r['prompt_count'];
            }
        }
        $students = array_values($byStudent);

        // Resolve the selected conversation (must belong to one of the
        // session's students) and load its transcript.
        $selected = null;
        if ($conversationId > 0) {
            foreach ($students as $stu) {
                foreach ($stu['conversations'] as $conv) {
                    if ($conv['id'] === $conversationId) {
                        $selected = [
                            'conversationId'   => $conversationId,
                            'conversationName' => $conv['name'],
                            'studentName'      => $stu['name'],
                            'transcript'       => array_map(
                                static fn (array $r): array => [
                                    'prompt'   => (string) $r['prompt'],
                                    'response' => $r['response'] !== null ? (string) $r['response'] : '',
                                    'model'    => (string) $r['model_name'],
                                    'sentAt'   => (new DateTimeImmutable((string) $r['sent_at']))->format('d/m/Y H:i'),
                                ],
                                $this->sessions->interactionsOfConversation($conversationId, $sessionId)
                            ),
                        ];
                        break 2;
                    }
                }
            }
        }

        return [
            'id'           => $sessionId,
            'name'         => $session->name(),
            'accessCode'   => $session->accessCodeFormatted() ?? '',
            'statusLabel'  => $computed->label(),
            'statusClass'  => $computed->badgeClass(),
            'studentCount' => count($students),
            'students'     => $students,
            'selected'     => $selected,
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
            // Access code is generated by the DB trigger once the session
            // is SCHEDULED/ACTIVE; null while it stays a draft.
            null,
            $startsAt,
            $endsAt,
            null,
            $data['prePrompt'],
            $data['postPrompt'],
            $data['instructions'],
            $data['maxInputSize'] ?? null,
            $data['maxTokens'] ?? null,
            $data['documentsMaxBytes'] ?? null,
        );

        $result = $this->sessions->insert($session->toRow());
        $session->assignId($result['id']);
        $session->assignAccessCode($result['access_code']);
        $this->sessions->setAuthorizedModels($result['id'], $data['modelIds']);
        $this->sessions->setAuthorizedFormats($result['id'], $data['documentsFormats'] ?? []);

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
        $session->reconfigure(
            $data['prePrompt'],
            $data['postPrompt'],
            $data['instructions'],
            $data['maxTokens'] ?? null,
            $data['maxInputSize'] ?? null,
            $now,
            $data['documentsMaxBytes'] ?? null
        );

        $this->sessions->update($id, $session->toRow());
        $this->sessions->setAuthorizedModels($id, $data['modelIds']);
        $this->sessions->setAuthorizedFormats($id, $data['documentsFormats'] ?? []);
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

        $sessionId = (int) $session->id();

        // A student deactivated by the teacher cannot re-join.
        if (
            $this->enrollments->exists($studentUserId, $sessionId)
            && !$this->enrollments->isActive($studentUserId, $sessionId)
        ) {
            throw SessionException::notAvailable("Vous avez été retiré de cette session par l'enseignant.");
        }

        $alreadyJoined = $this->enrollments->exists($studentUserId, $sessionId);
        if (!$alreadyJoined) {
            $this->enrollments->enroll($studentUserId, $sessionId);
        }

        // Land on the student's existing conversation, or create the first
        // one ("SESSION - CODE #1"). They can add more from the chat.
        $conversationId = $this->conversations->findIdByUserAndSession($studentUserId, $sessionId);
        if ($conversationId === null) {
            // conversations.model_id is NOT NULL: use a model authorised for
            // the session, falling back to any active model.
            $modelId = $this->sessions->firstModelForSession($sessionId) ?? $this->models->firstActiveId();
            if ($modelId === null) {
                throw new \RuntimeException('Aucun modèle disponible pour cette session.');
            }

            $code    = $session->accessCodeFormatted() ?? ('S' . $sessionId);
            $created = $this->conversations->newConversation(
                $studentUserId,
                $modelId,
                $sessionId,
                'SESSION - ' . $code . ' #1'
            );
            $conversationId = $created !== null ? (int) $created['id'] : null;
        }

        return [
            'conversationId' => $conversationId,
            'alreadyJoined'  => $alreadyJoined,
            'sessionName'    => $session->name(),
        ];
    }

    /**
     * Publishes a DRAFT resource the teacher owns (sets state to PUBLISHED).
     *
     * @throws \RuntimeException  On ownership mismatch or wrong state
     */
    public function publish(int $resourceId, int $teacherId): void
    {
        $row = $this->loadOwned($resourceId, $teacherId);
 
        if ($row['state'] === 'PUBLISHED') {
            throw new \RuntimeException('Cette ressource est déjà publiée.');
        }
        if ($row['state'] === 'ARCHIVED') {
            throw new \RuntimeException('Une ressource archivée ne peut pas être publiée directement. Restaurez-la d\'abord.');
        }
 
        $this->resources->publish($resourceId);
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

    /**
     * @return list<array<string, mixed>>
     */
    private function modelOptions(int $depId): array
    {
        return array_map(
            static fn (array $m): array => [
                'id'            => (int) $m['id'],
                'name'          => (string) $m['name'],
                'size'          => $m['size'] ?? null,
                'contextWindow' => isset($m['context_window']) && $m['context_window'] !== null ? (int) $m['context_window'] : null,
            ],
            $this->models->findAllActiveBySession(null,$depId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(Session $session, DateTimeImmutable $now): array
    {
        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);
        $resource = $this->resources->findById($session->resourceId());

        return [
            'id'                => (int) $session->id(),
            'name'              => $session->name(),
            'typeLabel'         => $session->type()->label(),
            'typeClass'         => $session->type()->badgeClass(),
            'statusLabel'       => $computed->label(),
            'statusClass'       => $computed->badgeClass(),
            'accessCode'        => $session->accessCodeFormatted() ?? '',
            'startsAtFormatted' => $session->startsAt()?->format('d/m/Y H:i'),
            'endsAtFormatted'   => $session->endsAt()?->format('d/m/Y H:i'),
            'canEdit'           => $actions['can_edit'],
            'canStart'          => $actions['can_start'],
            'canEnd'            => $actions['can_end'],
            'canCancel'         => $actions['can_cancel'],
            'resourceName'     => $resource !== null ? (string) $resource['name'] : '—',
        ];
    }
}