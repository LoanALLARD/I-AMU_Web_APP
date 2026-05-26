<?php

/**
 * I-AMU - Fichier de configuration global
 * Ce fichier est paramétrable par l'administrateur.
 */

return [

    // ─── Application ───────────────────────────────────────────
    'app' => [
        'name'      => 'I-AMU',
        'url'       => 'http://localhost:8080',
        'debug'     => true,
        'timezone'  => 'Europe/Paris',
        'lang'      => 'fr',
    ],

    // ─── Base de données (PostgreSQL) ──────────────────────────
    'database' => [
        'host'     => getenv('DB_HOST') ?: 'localhost',
        'port'     => getenv('DB_PORT') ?: '5432',
        'name'     => getenv('DB_NAME') ?: 'iamu',
        'user'     => getenv('DB_USER') ?: 'iamu_user',
        'password' => getenv('DB_PASS') ?: 'iamu_password',
    ],

    // ─── Domaines email autorisés ──────────────────────────────
    'domains' => [
        'student' => ['etu.univ-amu.fr'],
        'teacher' => ['univ-amu.fr'],
    ],

    // ─── Ollama (LLM local) ────────────────────────────────────
    'ollama' => [
        'host' => getenv('OLLAMA_HOST') ?: 'http://localhost:11434',
    ],

    // ─── Sessions & Auth ───────────────────────────────────────
    'auth' => [
        'session_lifetime'  => 3600,       // 1 heure
        'token_algo'        => 'sha256',
        'password_algo'     => PASSWORD_BCRYPT,
        'password_cost'     => 12,
    ],

    // ─── RGPD ──────────────────────────────────────────────────
    'rgpd' => [
        'data_retention_days'       => 365,     // Durée de conservation par défaut
        'conversation_archive_days' => 180,     // Archivage des conversations
        'require_consent'           => true,    // Bloquer l'accès si pas de consentement
    ],

    // ─── Contraintes par défaut pour les sessions ──────────────
    'session_defaults' => [
        'max_input_size'    => 2000,   // caractères max par prompt
        'max_duration'      => 7200,   // 2 heures max pour un examen
    ],
];
