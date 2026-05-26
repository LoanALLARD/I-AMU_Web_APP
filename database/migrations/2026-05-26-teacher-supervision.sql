-- ============================================================
-- Migration : supervision enseignant
-- Ajoute des colonnes pour le signalement, les commentaires "de marge"
-- et l'archivage des conversations en mode session/examen.
-- À appliquer une seule fois sur une DB existante.
-- ============================================================

BEGIN;

ALTER TABLE interaction
    ADD COLUMN IF NOT EXISTS teacher_flag        SMALLINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS teacher_flag_reason VARCHAR(100),
    ADD COLUMN IF NOT EXISTS teacher_comment     TEXT;

-- Index pour filtrer rapidement les prompts signalés dans une session
CREATE INDEX IF NOT EXISTS idx_interaction_teacher_flag
    ON interaction (teacher_flag)
    WHERE teacher_flag <> 0;

-- Statut de rendu d'une conversation (en mode cours/examen)
ALTER TABLE conversation
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP;

COMMIT;
