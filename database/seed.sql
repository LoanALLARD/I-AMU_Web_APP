-- ============================================================
-- I-AMU - Données initiales (seed)
-- ============================================================

BEGIN;

-- Modèles LLM
-- ⚠️  Aucun INSERT statique : la table `model` est peuplée automatiquement
-- depuis l'API Ollama (cf. LlmModel::syncFromOllama) au premier chargement
-- de la page /chat. La sync s'exécute aussi via le bouton "Synchroniser"
-- de la page /admin/models.

-- Lieu par défaut
INSERT INTO place (name, address, city, zip_code) VALUES
    ('Campus Luminy', '163 Avenue de Luminy', 'Marseille', '13009');

-- Département par défaut
INSERT INTO department (name, description, place_id) VALUES
    ('Informatique', 'Département Informatique - BUT', 1);

-- ──────────────────────────────────────────────────────
-- COMPTE ADMIN TEST  →  login: Admin  /  password: Admin
-- Hash bcrypt 12 rounds de 'Admin'
-- ⚠️  SUPPRIMER AVANT LA MISE EN PRODUCTION
-- ──────────────────────────────────────────────────────
INSERT INTO "user" (email, password_hash, first_name, last_name, is_active, gdpr_consent, gdpr_consent_at)
VALUES (
    'Admin',
    '$2b$12$YWwsIYCYs1NM4DrR/zeOvO0tTa81w9DWOVKmayuAsTZ0CrGE3y6Xq',
    'Admin', 'Test',
    TRUE, TRUE, CURRENT_TIMESTAMP
);
INSERT INTO administrator (user_id, is_super_admin) VALUES (1, TRUE);

COMMIT;
