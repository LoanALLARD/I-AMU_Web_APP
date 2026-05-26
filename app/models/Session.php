<?php

namespace App\Models;

use App\Core\Model;

class Session extends Model
{
    protected string $table = 'session';
    protected string $primaryKey = 'session_id';

    /**
     * Trouve une session par son code d'accès.
     */
    public function findByAccessCode(string $code): ?array
    {
        return $this->findOneBy(['access_code' => $code]);
    }

    /**
     * Crée une session et génère un code d'accès unique.
     */
    public function createSession(array $data): int
    {
        $data['access_code'] = $this->generateAccessCode();
        return $this->create($data);
    }

    /**
     * Vérifie si une session est actuellement active.
     */
    public function isActive(int $sessionId): bool
    {
        $session = $this->find($sessionId);
        if (!$session) {
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
