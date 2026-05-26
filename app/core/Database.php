<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton de connexion à la base de données PostgreSQL.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = Application::getInstance()->getConfig('database');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'],
            $config['port'],
            $config['name']
        );

        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (Application::getInstance()->getConfig('app.debug')) {
                throw $e;
            }
            die('Erreur de connexion à la base de données.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Exécute une requête préparée et retourne le statement.
     * Gère explicitement les types (bool, int, null) pour compatibilité PostgreSQL.
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        // Liaison explicite des paramètres pour éviter le bug PDO+PostgreSQL
        // où un booléen false est converti en chaîne vide (rejetée par PostgreSQL).
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . $key;

            if (is_bool($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
            } elseif (is_null($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Retourne le dernier ID inséré.
     */
    public function lastInsertId(string $sequence = ''): string
    {
        return $this->pdo->lastInsertId($sequence ?: null);
    }

    /**
     * Démarre une transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
