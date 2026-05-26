<?php

namespace App\Models;

use App\Core\Model;

/**
 * Jetons de réinitialisation de mot de passe.
 *
 * Le jeton est généré aléatoirement (random_bytes) et envoyé en clair
 * à l'utilisateur ; seul son hash SHA-256 est stocké, de sorte qu'une
 * fuite de la table ne permette pas de réutiliser les jetons.
 */
class PasswordReset extends Model
{
    protected string $table = 'password_reset';
    protected string $primaryKey = 'reset_id';

    /** Durée de validité d'un jeton, en secondes (1 heure). */
    public const TOKEN_TTL_SECONDS = 3600;

    /**
     * Génère un nouveau jeton pour un utilisateur et l'enregistre.
     * Retourne le jeton en clair (à transmettre à l'utilisateur via mail).
     *
     * Effet de bord : invalide tous les jetons précédents non utilisés
     * pour le même utilisateur, de façon à n'avoir qu'un seul lien actif.
     */
    public function createForUser(int $userId): string
    {
        // Invalide les anciens jetons : on les marque comme utilisés pour
        // garder une trace plutôt que de les supprimer.
        $this->db()->query(
            'UPDATE password_reset SET used_at = NOW()
             WHERE user_id = :uid AND used_at IS NULL',
            ['uid' => $userId]
        );

        $token = bin2hex(random_bytes(32)); // 64 caractères hex
        $hash  = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);

        $this->create([
            'user_id'    => $userId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Retourne le jeton actif correspondant au token en clair, ou null
     * s'il n'existe pas, est expiré ou déjà utilisé.
     */
    public function findValidByToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $hash = hash('sha256', $token);
        $row = $this->db()->query(
            'SELECT * FROM password_reset
             WHERE token_hash = :h AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1',
            ['h' => $hash]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Marque un jeton comme utilisé (consommé).
     */
    public function markUsed(int $resetId): bool
    {
        return $this->update($resetId, ['used_at' => date('Y-m-d H:i:s')]);
    }
}
