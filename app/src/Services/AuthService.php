<?php

namespace Services;

class AuthService {

    /**
     * IMPORTANT, ce fichier a été fait temporairement en dépanage par IA, il est totalement à
     * revoir car pour le moment on a pas de base
     */


    public function __construct() {
        // C'est ici que vous pourrez initialiser votre connexion
        // à la base de données plus tard.
    }

    /**
     * Gère la logique de connexion
     */
    public function login(string $email, string $password): bool {
        // TODO: À remplacer par votre vraie logique de base de données
        // 1. Chercher l'utilisateur dans la BDD via son email
        // 2. Vérifier le mot de passe avec password_verify()
        // 3. Créer la session

        // Faux compte pour tester que votre application fonctionne :
        if ($email === 'test@etu.univ-amu.fr' && $password === 'azerty123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['token']   = bin2hex(random_bytes(16)); // Faux token de sécurité
            return true;
        }

        return false; // Échec de la connexion
    }

    /**
     * Gère la logique d'inscription
     */
    public function register(array $data): bool {
        // TODO: Logique d'inscription
        // 1. Vérifier si l'email existe déjà
        // 2. Hacher le mot de passe : password_hash($data['password'], PASSWORD_DEFAULT)
        // 3. Sauvegarder en base de données

        return true;
    }

    /**
     * Gère la déconnexion
     */
    public function logout(): void {
        session_unset();
        session_destroy();
    }
}