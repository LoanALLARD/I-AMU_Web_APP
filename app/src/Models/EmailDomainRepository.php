<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for the `email_domain_configs` table.
 *
 * This table is the source of truth for which email domains may register
 * and which role they map to (an admin can add/disable domains without a
 * code change). AuthService drives the role resolution; the SQL lives here.
 */
class EmailDomainRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns the role configured for an active domain, or null when the
     * domain is unknown or disabled. The stored role is the SQL enum value
     * (UPPERCASE, e.g. 'STUDENT' / 'TEACHER'); the caller maps it.
     */
    public function findRoleByDomain(string $domain): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT role
             FROM email_domain_configs
             WHERE domain = :domain AND is_active = TRUE'
        );
        $stmt->execute(['domain' => $domain]);

        $role = $stmt->fetchColumn();

        return $role === false ? null : (string) $role;
    }

    /**
     * Returns the id of the laboratory whose active domain matches, or null
     * when the domain is unknown/disabled or no lab is attached to it. This is
     * how a researcher's lab is derived from their email at registration.
     */
    public function findLaboratoryIdByDomain(string $domain): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.id
             FROM email_domain_configs edc
             JOIN laboratories l ON l.email_domain_config_id = edc.id
             WHERE edc.domain = :domain AND edc.is_active = TRUE'
        );
        $stmt->execute(['domain' => $domain]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
