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

    /**
     * Allowed role values from the SQL enum `domain_role_type`, in declared order.
     *
     * @return list<string>
     */
    public function findRoleValues(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.enumlabel
             FROM pg_enum e
             JOIN pg_type t ON t.oid = e.enumtypid
             WHERE t.typname = 'domain_role_type'
             ORDER BY e.enumsortorder"
        );

        return array_map(
            static fn ($label): string => (string) $label,
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /**
     * Every configured domain (active and inactive), newest first.
     *
     * @return list<array{id:int, domain:string, role:string, is_active:bool}>
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, domain, role, is_active
             FROM email_domain_configs
             ORDER BY id DESC'
        );

        $rows = $stmt->fetchAll();

        return array_map(
            static fn (array $row): array => [
                'id'        => (int) $row['id'],
                'domain'    => (string) $row['domain'],
                'role'      => (string) $row['role'],
                'is_active' => (bool) $row['is_active'],
            ],
            $rows
        );
    }

    /**
     * @return array{id:int, domain:string, role:string, is_active:bool}|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, domain, role, is_active
             FROM email_domain_configs WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id'        => (int) $row['id'],
            'domain'    => (string) $row['domain'],
            'role'      => (string) $row['role'],
            'is_active' => (bool) $row['is_active'],
        ];
    }

    /** True when the domain already exists, whatever its state. */
    public function existsByDomain(string $domain): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM email_domain_configs WHERE domain = :domain'
        );
        $stmt->execute(['domain' => $domain]);

        return $stmt->fetchColumn() !== false;
    }

    /** Inserts a domain and returns its id; `addedById` is stored for traceability. */
    public function add(string $domain, string $role, int $addedById): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_domain_configs (added_by_id, domain, role, is_active)
             VALUES (:added_by, :domain, :role, TRUE)
             RETURNING id'
        );
        $stmt->execute([
            'added_by' => $addedById,
            'domain'   => $domain,
            'role'     => $role,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** Enables or disables a domain (the panel's soft "delete"). */
    public function setActive(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE email_domain_configs SET is_active = :active WHERE id = :id'
        );
        // Bind as bool: in execute() PDO sends false as '', which PG rejects for BOOLEAN.
        $stmt->bindValue('active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
