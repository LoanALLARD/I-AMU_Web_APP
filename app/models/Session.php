<?php

namespace App\Models;

use App\Core\Model;

class Session extends Model
{
    protected string $table = 'session';
    protected string $primaryKey = 'session_id';

    // Statuts stockés en base. ACTIVE/ENDED sont aussi des valeurs valides
    // de l'ENUM SQL, mais on ne les écrit pas : on les dérive de
    // starts_at/ends_at au moment de l'affichage (cf. computedStatus()).
    public const STATUS_DRAFT     = 'DRAFT';
    public const STATUS_SCHEDULED = 'SCHEDULED';
    public const STATUS_ACTIVE    = 'ACTIVE';
    public const STATUS_ENDED     = 'ENDED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Trouve une session par son code d'accès.
     */
    public function findByAccessCode(string $code): ?array
    {
        return $this->findOneBy(['access_code' => $code]);
    }

    /**
     * Crée une session. Si `access_code` est déjà présent dans $data et
     * encore disponible, il est conservé (utile pour afficher le code avant
     * la création côté UI). Sinon un nouveau code est généré.
     *
     * Le statut initial est DRAFT si starts_at est vide, SCHEDULED sinon.
     */
    public function createSession(array $data): int
    {
        $proposed = $data['access_code'] ?? null;
        if (!$proposed || $this->findByAccessCode($proposed)) {
            $data['access_code'] = $this->generateAccessCode();
        }
        if (!isset($data['status'])) {
            $data['status'] = empty($data['starts_at'])
                ? self::STATUS_DRAFT
                : self::STATUS_SCHEDULED;
        }
        return $this->create($data);
    }

    /**
     * Met à jour les champs modifiables d'une session. Le statut est recalculé
     * si starts_at change (DRAFT ↔ SCHEDULED). On ne touche pas à CANCELLED/ENDED.
     */
    public function updateSession(int $sessionId, array $data): bool
    {
        if (array_key_exists('starts_at', $data) && !isset($data['status'])) {
            $current = $this->find($sessionId);
            $isFinal = $current && in_array(
                $current['status'] ?? '',
                [self::STATUS_CANCELLED, self::STATUS_ENDED],
                true
            );
            if (!$isFinal) {
                $data['status'] = empty($data['starts_at'])
                    ? self::STATUS_DRAFT
                    : self::STATUS_SCHEDULED;
            }
        }
        return $this->update($sessionId, $data);
    }

    /**
     * Annule une session. Ne s'applique pas aux sessions déjà terminées.
     */
    public function cancel(int $sessionId): bool
    {
        return $this->update($sessionId, ['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Démarre une session manuellement : passe le statut à ACTIVE et,
     * si la session n'a pas encore de starts_at, le fixe à NOW.
     * Refuse les sessions déjà terminées ou annulées.
     */
    public function start(int $sessionId): bool
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return false;
        }
        $status = $session['status'] ?? self::STATUS_SCHEDULED;
        if (in_array($status, [self::STATUS_ENDED, self::STATUS_CANCELLED], true)) {
            return false;
        }

        $data = ['status' => self::STATUS_ACTIVE];
        if (empty($session['starts_at'])) {
            $data['starts_at'] = date('Y-m-d H:i:s');
        }
        return $this->update($sessionId, $data);
    }

    /**
     * Termine une session manuellement : passe le statut à ENDED et,
     * si ends_at est vide, le fixe à NOW.
     */
    public function end(int $sessionId): bool
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return false;
        }
        if (($session['status'] ?? null) === self::STATUS_CANCELLED) {
            return false;
        }

        $data = ['status' => self::STATUS_ENDED];
        if (empty($session['ends_at'])) {
            $data['ends_at'] = date('Y-m-d H:i:s');
        }
        return $this->update($sessionId, $data);
    }

    /**
     * Indique quelles transitions sont autorisées depuis l'état courant.
     * Sert à l'affichage conditionnel des boutons d'action.
     *
     * @return array{can_edit:bool, can_start:bool, can_end:bool, can_cancel:bool}
     */
    public function availableActions(array $session): array
    {
        $status = $this->computedStatus($session);
        return [
            'can_edit'   => $this->canBeModified($session),
            'can_start'  => in_array($status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true),
            'can_end'    => $status === self::STATUS_ACTIVE,
            'can_cancel' => !in_array($status, [self::STATUS_ENDED, self::STATUS_CANCELLED], true),
        ];
    }

    /**
     * Indique si une session peut être modifiée par l'enseignant.
     * Règle : statut DRAFT ou SCHEDULED, et starts_at pas encore atteint
     * (les DRAFT sans starts_at restent modifiables).
     */
    public function canBeModified(array $session): bool
    {
        $status = $session['status'] ?? self::STATUS_SCHEDULED;
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true)) {
            return false;
        }
        if (empty($session['starts_at'])) {
            return true;
        }
        return strtotime($session['starts_at']) > time();
    }

    /**
     * Statut "runtime" pour l'affichage : combine le statut stocké avec
     * l'horloge pour dériver ACTIVE/ENDED depuis SCHEDULED.
     */
    public function computedStatus(array $session): string
    {
        $status = $session['status'] ?? self::STATUS_SCHEDULED;
        if ($status !== self::STATUS_SCHEDULED) {
            return $status;
        }
        $now = time();
        $start = !empty($session['starts_at']) ? strtotime($session['starts_at']) : null;
        $end   = !empty($session['ends_at'])   ? strtotime($session['ends_at'])   : null;
        if ($end !== null && $now > $end) {
            return self::STATUS_ENDED;
        }
        if ($start !== null && $now >= $start) {
            return self::STATUS_ACTIVE;
        }
        return self::STATUS_SCHEDULED;
    }

    /**
     * Génère un code d'accès unique sans le persister, pour preview UI.
     */
    public function previewAccessCode(): string
    {
        return $this->generateAccessCode();
    }

    /**
     * Vérifie si une session est actuellement active.
     * Une session CANCELLED ou sans dates n'est jamais active.
     */
    public function isActive(int $sessionId): bool
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return false;
        }
        if (($session['status'] ?? null) === self::STATUS_CANCELLED) {
            return false;
        }
        if (empty($session['starts_at']) || empty($session['ends_at'])) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        return ($session['starts_at'] <= $now) && ($session['ends_at'] >= $now);
    }

    /**
     * Récupère les modèles autorisés pour une session.
     */
    public function getAuthorizedModels(int $sessionId): array
    {
        $sql = "SELECT m.* FROM model m
                JOIN authorizes a ON m.model_id = a.model_id
                WHERE a.session_id = :sid AND m.is_active = true";
        return $this->db()->query($sql, ['sid' => $sessionId])->fetchAll();
    }

    /**
     * Autorise un modèle pour une session.
     */
    public function authorizeModel(int $sessionId, int $modelId): void
    {
        $this->db()->query(
            'INSERT INTO authorizes (session_id, model_id) VALUES (:sid, :mid) ON CONFLICT DO NOTHING',
            ['sid' => $sessionId, 'mid' => $modelId]
        );
    }

    /**
     * Remplace l'ensemble des modèles autorisés pour une session.
     * Utile lors d'un update où l'enseignant ajuste la sélection.
     */
    public function setAuthorizedModels(int $sessionId, array $modelIds): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->query(
                'DELETE FROM authorizes WHERE session_id = :sid',
                ['sid' => $sessionId]
            );
            foreach ($modelIds as $modelId) {
                $this->authorizeModel($sessionId, (int) $modelId);
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Récupère les sessions d'une ressource (cours).
     */
    public function findByResource(int $resourceId): array
    {
        return $this->findBy(['resource_id' => $resourceId], 'starts_at DESC');
    }

    /**
     * Génère un code d'accès unique à 6 caractères.
     */
    private function generateAccessCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $exists = $this->findByAccessCode($code);
        } while ($exists);

        return $code;
    }
}
