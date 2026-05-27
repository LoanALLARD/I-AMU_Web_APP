<?php

/**
 * I-AMU - Script de création du compte admin de test
 * Exécuter UNE SEULE FOIS : php create_test_admin.php
 * 
 * Identifiants créés :
 *   Email    : admin
 *   Password : Admin
 */

require_once __DIR__ . '/app/autoload.php';

$app = \App\Core\Application::getInstance();
$db  = \App\Core\Database::getInstance();

// Email en minuscules : cohérent avec la normalisation strtolower
// appliquée par AuthService::register/login.
$email    = 'admin';
$password = 'Admin';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    // Vérifier si le compte existe déjà
    $existing = $db->query('SELECT user_id FROM "user" WHERE email = :email', ['email' => $email])->fetch();

    if ($existing) {
        // Mettre à jour le mot de passe si le compte existe déjà
        $db->query(
            'UPDATE "user" SET password_hash = :hash, is_active = true, gdpr_consent = true WHERE email = :email',
            ['hash' => $hash, 'email' => $email]
        );
        $userId = $existing['user_id'];
        echo "✅ Compte existant mis à jour (user_id: $userId)\n";
    } else {
        // Créer l'utilisateur
        $db->query(
            'INSERT INTO "user" (email, password_hash, first_name, last_name, is_active, gdpr_consent, gdpr_consent_at)
             VALUES (:email, :hash, :fn, :ln, true, true, NOW())',
            ['email' => $email, 'hash' => $hash, 'fn' => 'Admin', 'ln' => 'Test']
        );

        $userId = (int) $db->lastInsertId('user_user_id_seq');

        // Lui donner le rôle admin
        $db->query(
            'INSERT INTO administrator (user_id, is_super_admin) VALUES (:uid, true) ON CONFLICT DO NOTHING',
            ['uid' => $userId]
        );

        echo "✅ Compte admin de test créé (user_id: $userId)\n";
    }

    // S'assurer que le rôle admin est bien présent
    $db->query(
        'INSERT INTO administrator (user_id, is_super_admin) VALUES (:uid, true) ON CONFLICT DO NOTHING',
        ['uid' => $userId]
    );

    echo "\n";
    echo "══════════════════════════════════════\n";
    echo "  Identifiants du compte de test\n";
    echo "══════════════════════════════════════\n";
    echo "  Email    : Admin\n";
    echo "  Password : Admin\n";
    echo "══════════════════════════════════════\n";
    echo "  ⚠️  À SUPPRIMER AVANT LA MISE EN PROD\n";
    echo "══════════════════════════════════════\n\n";

} catch (\Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
