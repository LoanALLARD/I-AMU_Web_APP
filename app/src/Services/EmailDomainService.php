<?php

declare(strict_types=1);

namespace Services;

use Models\EmailDomainRepository;
use PDO;

/**
 * Super admin management of authorized email domains (`email_domain_configs`).
 * "Removing" a domain disables it (is_active = FALSE), not a DELETE, to keep
 * its history and laboratory link.
 */
final class EmailDomainService
{
    private EmailDomainRepository $domains;

    public function __construct(PDO $pdo)
    {
        $this->domains = new EmailDomainRepository($pdo);
    }

    /**
     * @return list<array{id:int, domain:string, role:string, is_active:bool}>
     */
    public function list(): array
    {
        return $this->domains->findAll();
    }

    /**
     * Allowed role values, read from the SQL enum `domain_role_type`.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->domains->findRoleValues();
    }

    /**
     * Adds a domain; `addedById` (the acting super admin) is stored for traceability.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function add(string $domain, string $role, int $addedById): array
    {
        $domain = strtolower(trim($domain));
        $role   = strtoupper(trim($role));

        if ($domain === '') {
            return ['success' => false, 'error' => 'Le domaine est requis.'];
        }
        if (!in_array($role, $this->domains->findRoleValues(), true)) {
            return ['success' => false, 'error' => 'Le rôle sélectionné est invalide.'];
        }
        if (!$this->isValidDomain($domain)) {
            return ['success' => false, 'error' => 'Le domaine est invalide (ex. : univ-amu.fr).'];
        }
        if ($this->domains->existsByDomain($domain)) {
            return ['success' => false, 'error' => 'Ce domaine est déjà configuré.'];
        }

        $this->domains->add($domain, $role, $addedById);

        return ['success' => true];
    }

    /**
     * Changes an existing domain's role.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function changeRole(int $id, string $role): array
    {
        $role = strtoupper(trim($role));

        if ($this->domains->findById($id) === null) {
            return ['success' => false, 'error' => 'Domaine introuvable.'];
        }
        if (!in_array($role, $this->domains->findRoleValues(), true)) {
            return ['success' => false, 'error' => 'Le rôle sélectionné est invalide.'];
        }

        $this->domains->updateRole($id, $role);

        return ['success' => true];
    }

    /**
     * Enables or disables an existing domain.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function setActive(int $id, bool $isActive): array
    {
        if ($this->domains->findById($id) === null) {
            return ['success' => false, 'error' => 'Domaine introuvable.'];
        }

        $this->domains->setActive($id, $isActive);

        return ['success' => true];
    }

    /** Bare domain: no scheme/@, at least one dot, max 50 chars (column limit). */
    private function isValidDomain(string $domain): bool
    {
        if (strlen($domain) > 50) {
            return false;
        }

        return preg_match('/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$/', $domain) === 1;
    }
}
