-- Trigger behaviour tests using pgTAP.

BEGIN;

SELECT plan(12);

-- ============================================================
-- Fixtures
-- ============================================================

INSERT INTO places (id, name) VALUES (1, 'Campus Saint-Charles');
INSERT INTO departments (id, place_id, name) VALUES (1, 1, 'Informatique');
INSERT INTO users (id, email, password_hash) VALUES (1, 'prof@univ-amu.fr', 'hash');
INSERT INTO teachers (id) VALUES (1);
INSERT INTO resources (id, owner_id, department_id, code, name)
    VALUES (1, 1, 1, 'INF101', 'Bases de données');

-- ============================================================
-- Trigger: generate_session_access_code — generation cases
-- ============================================================

INSERT INTO sessions (id, resource_id, name, status)
    VALUES (1, 1, 'Session DRAFT', 'DRAFT');

SELECT is(
    (SELECT access_code FROM sessions WHERE id = 1),
    NULL,
    'DRAFT session: access_code stays NULL'
);

INSERT INTO sessions (id, resource_id, name, status)
    VALUES (2, 1, 'Session SCHEDULED', 'SCHEDULED');

SELECT matches(
    (SELECT access_code FROM sessions WHERE id = 2),
    '^[A-Z0-9]{6}$',
    'SCHEDULED session: access_code is generated and matches ^[A-Z0-9]{6}$'
);

INSERT INTO sessions (id, resource_id, name, status)
    VALUES (3, 1, 'Session ACTIVE', 'ACTIVE');

SELECT matches(
    (SELECT access_code FROM sessions WHERE id = 3),
    '^[A-Z0-9]{6}$',
    'ACTIVE session: access_code is generated'
);

SELECT isnt(
    (SELECT access_code FROM sessions WHERE id = 2),
    (SELECT access_code FROM sessions WHERE id = 3),
    'Two sessions do not share the same access_code'
);

INSERT INTO sessions (id, resource_id, name, status) VALUES
    (5, 1, 'Session ENDED',     'ENDED'),
    (6, 1, 'Session CANCELLED', 'CANCELLED');

SELECT results_eq(
    $$SELECT access_code FROM sessions WHERE id IN (5, 6) ORDER BY id$$,
    ARRAY[NULL::CHAR(6), NULL::CHAR(6)],
    'ENDED and CANCELLED sessions: access_code stays NULL'
);

-- ============================================================
-- Trigger: generate_session_access_code — preservation cases
-- ============================================================

INSERT INTO sessions (id, resource_id, name, status, access_code)
    VALUES (4, 1, 'Session with manual code', 'SCHEDULED', 'MANU01');

SELECT is(
    (SELECT access_code FROM sessions WHERE id = 4),
    'MANU01'::CHAR(6),
    'Pre-set access_code is preserved on SCHEDULED insert'
);

-- Existing code is NOT regenerated on a neutral UPDATE
-- (this is the critical case — the trigger fires on UPDATE too, but
-- should be a no-op when access_code is already set)
DO $$
DECLARE
    code_before CHAR(6);
    code_after CHAR(6);
BEGIN
    SELECT access_code INTO code_before FROM sessions WHERE id = 2;
    UPDATE sessions SET name = 'Renamed session' WHERE id = 2;
    SELECT access_code INTO code_after FROM sessions WHERE id = 2;
    PERFORM is(code_before, code_after, 'Neutral UPDATE on SCHEDULED session does not regenerate access_code');
END $$;

UPDATE sessions SET status = 'SCHEDULED' WHERE id = 1;

SELECT matches(
    (SELECT access_code FROM sessions WHERE id = 1),
    '^[A-Z0-9]{6}$',
    'DRAFT→SCHEDULED UPDATE: access_code is generated'
);

-- Transition SCHEDULED → ENDED does NOT clear the code
-- (business rule: an ended session should keep its historical code)
UPDATE sessions SET status = 'ENDED' WHERE id = 2;

SELECT isnt(
    (SELECT access_code FROM sessions WHERE id = 2),
    NULL,
    'SCHEDULED→ENDED UPDATE: access_code is kept (not cleared)'
);

-- ============================================================
-- Trigger: enforce_model_resource_access_same_dept
-- A resource-scoped model is shared only with resources of its
-- own department; cross-department or department-scoped models are
-- rejected.
-- ============================================================

-- A second resource in the SAME department, plus a resource in ANOTHER one.
INSERT INTO resources (id, owner_id, department_id, code, name)
    VALUES (2, 1, 1, 'INF102', 'Algorithmique');
INSERT INTO departments (id, place_id, name) VALUES (2, 1, 'Mathematiques');
INSERT INTO resources (id, owner_id, department_id, code, name)
    VALUES (3, 1, 2, 'MAT101', 'Analyse');

-- A resource-scoped model owned by resource 1 (department 1) and a
-- department-scoped model on department 1.
INSERT INTO models (id, resource_id, name, provider, max_tokens, context_window, api_url, adapter, is_shareable)
    VALUES (1, 1, 'res-model', 'ollama', 1024, 4096, 'http://x', 'ollama', TRUE);
INSERT INTO models (id, department_id, name, provider, max_tokens, context_window, api_url, adapter)
    VALUES (2, 1, 'dept-model', 'ollama', 1024, 4096, 'http://x', 'ollama');

SELECT lives_ok(
    $$INSERT INTO model_resource_accesses (model_id, resource_id) VALUES (1, 2)$$,
    'model_resource_accesses: share with a resource of the same department is accepted'
);

SELECT throws_ok(
    $$INSERT INTO model_resource_accesses (model_id, resource_id) VALUES (1, 3)$$,
    '23514',
    NULL,
    'model_resource_accesses: share with a resource of another department is rejected'
);

SELECT throws_ok(
    $$INSERT INTO model_resource_accesses (model_id, resource_id) VALUES (2, 2)$$,
    '23514',
    NULL,
    'model_resource_accesses: sharing a department-scoped model this way is rejected'
);

SELECT finish();

ROLLBACK;
