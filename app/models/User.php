<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class User extends Model
{
    protected string $table = 'user';
    protected string $primaryKey = 'user_id';

    /**
     * Trouve un utilisateur par email.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Crée un utilisateur avec attribution automatique des rôles.
     */
    public function register(array $data): int
    {
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Création de l'utilisateur de base
            $userId = $this->create([
                'email'         => $data['email'],
                'password_hash' => $data['password_hash'],
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'gdpr_consent'  => $data['gdpr_consent'] ?? false,
                'gdpr_consent_at' => $data['gdpr_consent'] ? date('Y-m-d H:i:s') : null,
            ]);

            // Attribution automatique du rôle selon le domaine email
            $domain = $this->extractDomain($data['email']);
            $config = \App\Core\Application::getInstance()->getConfig('domains');

            if (in_array($domain, $config['student'] ?? [], true)) {
                $db->query(
                    'INSERT INTO student (user_id, student_number) VALUES (:uid, :sn)',
                    ['uid' => $userId, 'sn' => $data['student_number'] ?? null]
                );
            }

            if (in_array($domain, $config['teacher'] ?? [], true)) {
                $db->query(
                    'INSERT INTO teacher (user_id, is_specialised, title) VALUES (:uid, false, null)',
                    ['uid' => $userId]
                );
            }

            $db->commit();
            return $userId;
        } catch (\Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Retourne les rôles d'un utilisateur.
     */
    public function getRoles(int $userId): array
    {
        $roles = [];
        $db = Database::getInstance();

        if ($db->query('SELECT 1 FROM student WHERE user_id = :id', ['id' => $userId])->fetch()) {
            $roles[] = 'student';
        }
        if ($db->query('SELECT 1 FROM teacher WHERE user_id = :id', ['id' => $userId])->fetch()) {
            $teacher = $db->query('SELECT is_specialised FROM teacher WHERE user_id = :id', ['id' => $userId])->fetch();
            $roles[] = 'teacher';
            if ($teacher && $teacher['is_specialised']) {
                $roles[] = 'teacher_specialised';
            }
        }
        if ($db->query('SELECT 1 FROM researcher WHERE user_id = :id', ['id' => $userId])->fetch()) {
            $roles[] = 'researcher';
        }
        if ($db->query('SELECT 1 FROM administrator WHERE user_id = :id', ['id' => $userId])->fetch()) {
            $roles[] = 'admin';
        }

        return $roles;
    }

    /**
     * Vérifie si le domaine email est autorisé.
     */
    public function isAllowedDomain(string $email): bool
    {
        $domain = $this->extractDomain($email);
        $config = \App\Core\Application::getInstance()->getConfig('domains');

        $allDomains = array_merge(
            $config['student'] ?? [],
            $config['teacher'] ?? []
        );

        return in_array($domain, $allDomains, true);
    }

    /**
     * Extrait le domaine d'un email.
     */
    private function extractDomain(string $email): string
    {
        return strtolower(substr($email, strpos($email, '@') + 1));
    }

    /**
     * Met à jour le consentement RGPD.
     */
    public function updateGdprConsent(int $userId, bool $consent): bool
    {
        return $this->update($userId, [
            'gdpr_consent'    => $consent,
            'gdpr_consent_at' => $consent ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Met à jour la date de dernière connexion.
     */
    public function updateLastLogin(int $userId): bool
    {
        return $this->update($userId, ['last_login' => date('Y-m-d H:i:s')]);
    }
}
