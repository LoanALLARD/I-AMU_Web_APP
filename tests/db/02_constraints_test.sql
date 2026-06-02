-- CHECK constraint tests.
-- Only the project's own business rules are covered. Native guarantees
-- (UNIQUE rejecting a duplicate, CHAR(6) truncation, NULL distinctness)
-- are Postgres behaviour, not our schema, so they are not re-tested here.

BEGIN;

SELECT plan(15);

-- ============================================================
-- Fixtures
-- ============================================================

INSERT INTO places (id, name) VALUES (1, 'Campus Luminy');
INSERT INTO departments (id, place_id, name) VALUES (1, 1, 'Informatique');
INSERT INTO users (id, email, password_hash) VALUES (1, 'teacher@univ-amu.fr', 'hash1');
INSERT INTO teachers (id) VALUES (1);
INSERT INTO resources (id, owner_id, department_id, code, name)
    VALUES (1, 1, 1, 'RES01', 'Algorithmique');

-- ============================================================
-- users: archive_duration_days must be > 0
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO users (email, password_hash, archive_duration_days)
      VALUES ('a@test.fr', 'h', 0)$$,
    '23514',
    NULL,
    'users.archive_duration_days = 0 is rejected'
);

SELECT lives_ok(
    $$INSERT INTO users (email, password_hash, archive_duration_days)
      VALUES ('c@test.fr', 'h', 30)$$,
    'users.archive_duration_days = 30 is accepted'
);

-- ============================================================
-- sessions: ends_at must be strictly greater than starts_at
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO sessions (resource_id, name, starts_at, ends_at)
      VALUES (1, 'S1', NOW(), NOW() - INTERVAL '1 hour')$$,
    '23514',
    NULL,
    'sessions: ends_at < starts_at is rejected'
);

SELECT throws_ok(
    $$INSERT INTO sessions (resource_id, name, starts_at, ends_at)
      VALUES (1, 'S2', NOW(), NOW())$$,
    '23514',
    NULL,
    'sessions: ends_at = starts_at is rejected'
);

SELECT lives_ok(
    $$INSERT INTO sessions (resource_id, name, starts_at, ends_at)
      VALUES (1, 'S3', NOW(), NOW() + INTERVAL '2 hours')$$,
    'sessions: valid date range is accepted'
);

-- ============================================================
-- sessions: access_code must match ^[A-Z0-9]{6}$
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO sessions (resource_id, name, access_code)
      VALUES (1, 'S4', 'abc123')$$,
    '23514',
    NULL,
    'sessions: lowercase access_code is rejected'
);

SELECT lives_ok(
    $$INSERT INTO sessions (resource_id, name, access_code)
      VALUES (1, 'S7', 'AB1234')$$,
    'sessions: valid access_code AB1234 is accepted'
);

-- ============================================================
-- sessions: max_input_size must be > 0
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO sessions (resource_id, name, max_input_size)
      VALUES (1, 'S8', 0)$$,
    '23514',
    NULL,
    'sessions.max_input_size = 0 is rejected'
);

-- ============================================================
-- models: max_tokens and context_window must be > 0
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO models (department_id, name, provider, max_tokens, context_window, api_url, adapter)
      VALUES (1, 'm1', 'ollama', 0, 4096, 'http://x', 'ollama')$$,
    '23514',
    NULL,
    'models.max_tokens = 0 is rejected'
);

SELECT throws_ok(
    $$INSERT INTO models (department_id, name, provider, max_tokens, context_window, api_url, adapter)
      VALUES (1, 'm2', 'ollama', 1024, 0, 'http://x', 'ollama')$$,
    '23514',
    NULL,
    'models.context_window = 0 is rejected'
);

-- ============================================================
-- models: resource-scoped model cannot be shareable
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO models (resource_id, name, provider, max_tokens, context_window, api_url, adapter, is_shareable)
      VALUES (1, 'm3', 'ollama', 1024, 4096, 'http://x', 'ollama', TRUE)$$,
    '23514',
    NULL,
    'models: resource-scoped model with is_shareable=TRUE is rejected'
);

-- ============================================================
-- models: scope — exactly one of resource_id / department_id
-- ============================================================

SELECT throws_ok(
    $$INSERT INTO models (name, provider, max_tokens, context_window, api_url, adapter)
      VALUES ('m4', 'ollama', 1024, 4096, 'http://x', 'ollama')$$,
    '23514',
    NULL,
    'models: no resource_id and no department_id is rejected'
);

SELECT throws_ok(
    $$INSERT INTO models (resource_id, department_id, name, provider, max_tokens, context_window, api_url, adapter)
      VALUES (1, 1, 'm5', 'ollama', 1024, 4096, 'http://x', 'ollama')$$,
    '23514',
    NULL,
    'models: resource_id AND department_id together is rejected'
);

-- ============================================================
-- interactions: user_feedback must be in (-1, 0, 1)
-- ============================================================

INSERT INTO models (id, department_id, name, provider, max_tokens, context_window, api_url, adapter)
    VALUES (1, 1, 'llama3', 'ollama', 4096, 8192, 'http://localhost:11434', 'ollama');
INSERT INTO conversations (id, user_id, name) VALUES (1, 1, 'Conv 1');

SELECT throws_ok(
    $$INSERT INTO interactions (model_id, conversation_id, prompt, user_feedback)
      VALUES (1, 1, 'Hello', 2)$$,
    '23514',
    NULL,
    'interactions.user_feedback = 2 is rejected'
);

SELECT lives_ok(
    $$INSERT INTO interactions (model_id, conversation_id, prompt, user_feedback)
      VALUES (1, 1, 'Hello', -1)$$,
    'interactions.user_feedback = -1 is accepted'
);

SELECT finish();

ROLLBACK;
