-- ON DELETE behaviour tests (CASCADE / RESTRICT / SET NULL).
-- A silent FK change here can break audit trails or allow forbidden deletions.

BEGIN;

SELECT plan(18);

-- ============================================================
-- Fixtures
-- ============================================================

INSERT INTO places (id, name) VALUES (1, 'Campus');
INSERT INTO departments (id, place_id, name) VALUES (1, 1, 'Informatique');
INSERT INTO laboratories (id, code, name) VALUES (1, 'LIF', 'LIF');

INSERT INTO super_administrators (id, email, password_hash) VALUES
    (1, 'sa1@amu.fr', 'h'),
    (2, 'sa2@amu.fr', 'h');

INSERT INTO users (id, email, password_hash) VALUES
    (10, 'teacher@univ-amu.fr',         'h'),  -- isolated teacher (no resource)
    (15, 'teacher-owner@univ-amu.fr',   'h'),  -- teacher that owns a resource
    (20, 'student@etu.univ-amu.fr',     'h'),
    (30, 'researcher@amu.fr',           'h'),
    (40, 'deptadmin@amu.fr',            'h'),
    (50, 'orphan@amu.fr',               'h');

INSERT INTO teachers (id) VALUES (10), (15);
INSERT INTO students (id) VALUES (20);
INSERT INTO researchers (id, approved_by_id, laboratory_id) VALUES (30, 1, 1);
INSERT INTO department_administrators (id, invited_by_id) VALUES (40, 1);

-- Resource is owned by user 15, NOT 10, so we can delete user 10 freely.
INSERT INTO resources (id, owner_id, department_id, code, name)
    VALUES (1, 15, 1, 'INF101', 'BDD');

INSERT INTO models (id, department_id, name, provider, max_tokens, context_window, api_url, adapter)
    VALUES (1, 1, 'llama3', 'ollama', 4096, 8192, 'http://x', 'ollama');

INSERT INTO sessions (id, resource_id, name, status) VALUES (1, 1, 'S1', 'DRAFT');
INSERT INTO conversations (id, user_id, model_id, name) VALUES (1, 50, 1, 'Conv');
INSERT INTO interactions (id, conversation_id, prompt)
    VALUES (1, 1, 'Hello');

-- ============================================================
-- CASCADE: user role rows disappear when the user is deleted
-- ============================================================

DELETE FROM users WHERE id = 10;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM teachers WHERE id = 10$$,
    ARRAY[0],
    'Deleting users cascades to teachers'
);

DELETE FROM users WHERE id = 20;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM students WHERE id = 20$$,
    ARRAY[0],
    'Deleting users cascades to students'
);

DELETE FROM users WHERE id = 30;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM researchers WHERE id = 30$$,
    ARRAY[0],
    'Deleting users cascades to researchers'
);

DELETE FROM users WHERE id = 40;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM department_administrators WHERE id = 40$$,
    ARRAY[0],
    'Deleting users cascades to department_administrators'
);

-- ============================================================
-- RESTRICT: cannot delete a user who owns conversations
-- (conversations.user_id is ON DELETE RESTRICT)
-- ============================================================

SELECT throws_ok(
    $$DELETE FROM users WHERE id = 50$$,
    '23503',
    NULL,
    'Cannot delete a user who has conversations (RESTRICT)'
);

-- ============================================================
-- RESTRICT: cannot delete a model still used by a conversation
-- (conversations.model_id is ON DELETE RESTRICT)
-- ============================================================

SELECT throws_ok(
    $$DELETE FROM models WHERE id = 1$$,
    '23503',
    NULL,
    'Cannot delete a model still used by a conversation (RESTRICT)'
);

-- ============================================================
-- CASCADE: deleting a conversation removes its interactions
-- ============================================================

DELETE FROM conversations WHERE id = 1;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM interactions WHERE conversation_id = 1$$,
    ARRAY[0],
    'Deleting a conversation cascades to its interactions'
);

-- After conversation gone, user can now be deleted
SELECT lives_ok(
    $$DELETE FROM users WHERE id = 50$$,
    'User without conversations can now be deleted'
);

-- ============================================================
-- RESTRICT: cannot delete a teacher who owns a resource
-- ============================================================

-- User 15 owns resource 1 (set in fixtures). Deleting the user cascades
-- to teachers, but resources.owner_id has ON DELETE RESTRICT — so the
-- whole chain must fail to protect the resource.
SELECT throws_ok(
    $$DELETE FROM users WHERE id = 15$$,
    '23503',
    NULL,
    'Cannot delete a user-teacher who owns a resource (RESTRICT via cascade)'
);

-- ============================================================
-- RESTRICT: cannot delete a session that has enrollments
-- ============================================================

INSERT INTO users (id, email, password_hash) VALUES (21, 's2@etu.univ-amu.fr', 'h');
INSERT INTO students (id) VALUES (21);
INSERT INTO sessions (id, resource_id, name, status)
    VALUES (2, 1, 'S with enrollment', 'SCHEDULED');
INSERT INTO enrollments (student_id, session_id) VALUES (21, 2);

SELECT throws_ok(
    $$DELETE FROM sessions WHERE id = 2$$,
    '23503',
    NULL,
    'Cannot delete a session that has enrollments (RESTRICT)'
);

-- But the student can be deleted: enrollment cascades on student side
DELETE FROM users WHERE id = 21;
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM enrollments WHERE session_id = 2$$,
    ARRAY[0],
    'Deleting a student cascades to enrollments on student side'
);

-- ============================================================
-- RESTRICT: cannot delete a resource that owns models or sessions
-- ============================================================

SELECT throws_ok(
    $$DELETE FROM resources WHERE id = 1$$,
    '23503',
    NULL,
    'Cannot delete a resource that has models (RESTRICT)'
);

-- ============================================================
-- SET NULL: researcher.approved_by_id is cleared when super admin is deleted
-- ============================================================

INSERT INTO users (id, email, password_hash) VALUES (31, 'r2@amu.fr', 'h');
INSERT INTO researchers (id, approved_by_id, laboratory_id) VALUES (31, 2, 1);

DELETE FROM super_administrators WHERE id = 2;

SELECT is(
    (SELECT approved_by_id FROM researchers WHERE id = 31),
    NULL,
    'Deleting super_admin sets researcher.approved_by_id to NULL'
);

-- ============================================================
-- SET NULL: department_administrator.invited_by_id is cleared
-- ============================================================

INSERT INTO super_administrators (id, email, password_hash) VALUES (3, 'sa3@amu.fr', 'h');
INSERT INTO users (id, email, password_hash) VALUES (41, 'da2@amu.fr', 'h');
INSERT INTO department_administrators (id, invited_by_id) VALUES (41, 3);

DELETE FROM super_administrators WHERE id = 3;

SELECT is(
    (SELECT invited_by_id FROM department_administrators WHERE id = 41),
    NULL,
    'Deleting super_admin sets department_administrators.invited_by_id to NULL'
);

-- ============================================================
-- SET NULL: email_domain_configs.added_by_id is cleared
-- ============================================================

INSERT INTO super_administrators (id, email, password_hash) VALUES (4, 'sa4@amu.fr', 'h');
INSERT INTO email_domain_configs (id, added_by_id, domain, role)
    VALUES (99, 4, 'test.fr', 'TEACHER');

DELETE FROM super_administrators WHERE id = 4;

SELECT is(
    (SELECT added_by_id FROM email_domain_configs WHERE id = 99),
    NULL,
    'Deleting super_admin sets email_domain_configs.added_by_id to NULL'
);

-- ============================================================
-- CASCADE on junction tables: removing a teacher clears teacher_resources
-- ============================================================

INSERT INTO users (id, email, password_hash) VALUES (12, 't3@univ-amu.fr', 'h');
INSERT INTO teachers (id) VALUES (12);
INSERT INTO teacher_resources (teacher_id, resource_id) VALUES (12, 1);

DELETE FROM users WHERE id = 12;

SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM teacher_resources WHERE teacher_id = 12$$,
    ARRAY[0],
    'Deleting a teacher cascades to teacher_resources'
);

-- ============================================================
-- RESTRICT on session_models (sessions side): cannot delete a session
-- that has assigned models
-- ============================================================

INSERT INTO sessions (id, resource_id, name, status)
    VALUES (3, 1, 'S with model', 'DRAFT');
INSERT INTO session_models (model_id, session_id) VALUES (1, 3);

SELECT throws_ok(
    $$DELETE FROM sessions WHERE id = 3$$,
    '23503',
    NULL,
    'Cannot delete a session that has assigned models (RESTRICT)'
);

-- Deleting a model cascades to session_models on the model side.
-- conversations.model_id is RESTRICT, but conversation 1 (the only one
-- referencing model 1) was already removed above, so nothing blocks the delete.
DELETE FROM session_models WHERE session_id = 3;
DELETE FROM sessions WHERE id = 3;
INSERT INTO sessions (id, resource_id, name, status)
    VALUES (4, 1, 'S with model 2', 'DRAFT');
INSERT INTO session_models (model_id, session_id) VALUES (1, 4);

DELETE FROM models WHERE id = 1;

SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM session_models WHERE model_id = 1$$,
    ARRAY[0],
    'Deleting a model cascades to session_models (model side)'
);

SELECT finish();

ROLLBACK;
