-- ============================================================
-- Migration : table password_reset
-- Stocke les jetons de réinitialisation de mot de passe émis par
-- le flow "Mot de passe oublié". Le jeton est envoyé en clair par
-- mail (ou affiché en debug) et stocké en SHA-256 pour qu'une fuite
-- de la table ne suffise pas à le réutiliser.
-- ============================================================

BEGIN;

CREATE TABLE IF NOT EXISTS password_reset (
    reset_id     SERIAL PRIMARY KEY,
    user_id      INT NOT NULL REFERENCES "user"(user_id) ON DELETE CASCADE,
    token_hash   CHAR(64) NOT NULL UNIQUE,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at   TIMESTAMP NOT NULL,
    used_at      TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_reset_user      ON password_reset (user_id);
CREATE INDEX IF NOT EXISTS idx_password_reset_expires   ON password_reset (expires_at);

COMMIT;
