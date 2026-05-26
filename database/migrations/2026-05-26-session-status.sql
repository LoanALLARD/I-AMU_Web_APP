-- ============================================================
-- Migration : statut explicite des sessions
-- Ajoute un type ENUM session_status et une colonne status sur la
-- table session. Permet de distinguer DRAFT / SCHEDULED / ACTIVE /
-- ENDED / CANCELLED, et d'autoriser/refuser la modification.
--
-- Note : ACTIVE et ENDED sont dérivables de starts_at/ends_at, mais
-- stocker l'ensemble simplifie les filtres et permet de gérer
-- explicitement DRAFT (pas encore planifiée) et CANCELLED (annulée
-- par l'enseignant).
-- À appliquer une seule fois sur une DB existante.
-- ============================================================

BEGIN;

-- Type ENUM (création conditionnelle pour idempotence)
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'session_status') THEN
        CREATE TYPE session_status AS ENUM (
            'DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED'
        );
    END IF;
END$$;

ALTER TABLE session
    ADD COLUMN IF NOT EXISTS status session_status NOT NULL DEFAULT 'SCHEDULED';

-- Backfill cohérent pour les sessions existantes :
--   - sans starts_at  -> DRAFT
--   - déjà terminées  -> ENDED
--   - en cours        -> SCHEDULED (sera vu comme ACTIVE via le statut runtime)
--   - sinon (futures) -> SCHEDULED (déjà la valeur par défaut)
UPDATE session SET status = 'DRAFT' WHERE starts_at IS NULL;
UPDATE session SET status = 'ENDED' WHERE ends_at IS NOT NULL AND ends_at < NOW();

CREATE INDEX IF NOT EXISTS idx_session_status ON session(status);

COMMIT;
