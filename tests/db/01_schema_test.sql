-- Schema structure tests.
-- Asserts that tables, columns, types, keys, constraints, ENUMs and triggers
-- declared in database/schema/ are actually present. Runs against an empty
-- schema; it inserts no data. A drifting migration that drops a column or
-- weakens a constraint fails here with a clear message instead of crashing a
-- behavioural test downstream with an obscure error.

BEGIN;

SELECT plan(112);

-- ============================================================
-- Tables exist
-- ============================================================

SELECT has_table('users');
SELECT has_table('laboratories');
SELECT has_table('super_administrators');
SELECT has_table('teachers');
SELECT has_table('students');
SELECT has_table('places');
SELECT has_table('departments');
SELECT has_table('researchers');
SELECT has_table('department_administrators');
SELECT has_table('email_domain_configs');
SELECT has_table('resources');
SELECT has_table('models');
SELECT has_table('sessions');
SELECT has_table('conversations');
SELECT has_table('interactions');
SELECT has_table('teacher_resources');
SELECT has_table('student_resources');
SELECT has_table('session_models');
SELECT has_table('department_administrator_assignments');
SELECT has_table('enrollments');
SELECT has_table('researcher_authorizations');
SELECT has_table('model_department_accesses');

-- ============================================================
-- ENUM types exist with exactly the expected labels
-- ============================================================

SELECT has_type('theme_type');
SELECT enum_has_labels('theme_type', ARRAY['LIGHT', 'DARK']);

SELECT has_type('resource_state_type');
SELECT enum_has_labels('resource_state_type', ARRAY['DRAFT', 'PUBLISHED', 'ARCHIVED']);

SELECT has_type('domain_role_type');
SELECT enum_has_labels('domain_role_type', ARRAY['STUDENT', 'TEACHER']);

SELECT has_type('session_type');
SELECT enum_has_labels('session_type', ARRAY['EXAM', 'TUTORIAL', 'LAB', 'FREE_STUDY']);

SELECT has_type('session_status_type');
SELECT enum_has_labels('session_status_type', ARRAY['DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED']);

-- ============================================================
-- Primary keys
-- ============================================================

SELECT has_pk('users');
SELECT has_pk('laboratories');
SELECT has_pk('super_administrators');
SELECT has_pk('teachers');
SELECT has_pk('students');
SELECT has_pk('places');
SELECT has_pk('departments');
SELECT has_pk('researchers');
SELECT has_pk('department_administrators');
SELECT has_pk('email_domain_configs');
SELECT has_pk('resources');
SELECT has_pk('models');
SELECT has_pk('sessions');
SELECT has_pk('conversations');
SELECT has_pk('interactions');

-- Composite PKs on junction tables
SELECT col_is_pk('teacher_resources', ARRAY['teacher_id', 'resource_id']);
SELECT col_is_pk('student_resources', ARRAY['student_id', 'resource_id']);
SELECT col_is_pk('session_models', ARRAY['model_id', 'session_id']);
SELECT col_is_pk('department_administrator_assignments', ARRAY['department_id', 'administrator_id']);
SELECT col_is_pk('enrollments', ARRAY['student_id', 'session_id']);
SELECT col_is_pk('researcher_authorizations', ARRAY['researcher_id', 'department_id']);
SELECT col_is_pk('model_department_accesses', ARRAY['model_id', 'department_id']);

-- ============================================================
-- Key columns and their types
-- ============================================================

SELECT col_type_is('users', 'email', 'character varying(255)');
SELECT col_type_is('users', 'theme', 'theme_type');
SELECT col_type_is('users', 'archive_duration_days', 'smallint');
SELECT col_type_is('students', 'year', 'smallint');
SELECT col_type_is('sessions', 'status', 'session_status_type');
SELECT col_type_is('sessions', 'access_code', 'character(6)');
SELECT col_type_is('sessions', 'type', 'session_type');
SELECT col_type_is('resources', 'state', 'resource_state_type');
SELECT col_type_is('email_domain_configs', 'role', 'domain_role_type');
SELECT col_type_is('interactions', 'user_feedback', 'smallint');

-- ============================================================
-- NOT NULL on structurally required columns
-- ============================================================

SELECT col_not_null('users', 'email');
SELECT col_not_null('users', 'password_hash');
SELECT col_not_null('resources', 'owner_id');
SELECT col_not_null('resources', 'department_id');
SELECT col_not_null('sessions', 'resource_id');
SELECT col_not_null('sessions', 'status');
SELECT col_not_null('conversations', 'user_id');
SELECT col_not_null('interactions', 'model_id');
SELECT col_not_null('interactions', 'conversation_id');
SELECT col_not_null('interactions', 'prompt');
SELECT col_not_null('researchers', 'laboratory_id');

-- ============================================================
-- Defaults
-- ============================================================

SELECT col_has_default('users', 'is_active');
SELECT col_has_default('teachers', 'is_specialised');
SELECT col_has_default('sessions', 'status');
SELECT col_has_default('resources', 'state');
SELECT col_has_default('models', 'is_shareable');
SELECT col_has_default('conversations', 'is_archived');

-- ============================================================
-- UNIQUE constraints
-- ============================================================

SELECT col_is_unique('users', 'email');
SELECT col_is_unique('super_administrators', 'email');
SELECT col_is_unique('laboratories', 'code');
SELECT col_is_unique('sessions', 'access_code');

-- ============================================================
-- Foreign keys (presence + reference target)
-- ============================================================

SELECT has_fk('teachers');
SELECT fk_ok('teachers', 'id', 'users', 'id');

SELECT has_fk('students');
SELECT fk_ok('students', 'id', 'users', 'id');

SELECT has_fk('researchers');
SELECT fk_ok('researchers', 'laboratory_id', 'laboratories', 'id');

SELECT has_fk('resources');
SELECT fk_ok('resources', 'owner_id', 'teachers', 'id');
SELECT fk_ok('resources', 'department_id', 'departments', 'id');

SELECT has_fk('sessions');
SELECT fk_ok('sessions', 'resource_id', 'resources', 'id');

SELECT has_fk('conversations');
SELECT fk_ok('conversations', 'user_id', 'users', 'id');
SELECT fk_ok('conversations', 'session_id', 'sessions', 'id');

SELECT has_fk('interactions');
SELECT fk_ok('interactions', 'model_id', 'models', 'id');
SELECT fk_ok('interactions', 'conversation_id', 'conversations', 'id');

-- ============================================================
-- CHECK constraints (presence)
-- ============================================================

SELECT has_check('users');
SELECT has_check('students');
SELECT has_check('models');
SELECT has_check('sessions');
SELECT has_check('interactions');

-- ============================================================
-- Triggers from database/schema/02_triggers.sql
-- ============================================================

SELECT has_trigger('sessions', 'trg_generate_session_access_code');
SELECT has_trigger('students', 'trg_students_role_exclusivity');
SELECT has_trigger('teachers', 'trg_teachers_role_exclusivity');
SELECT has_trigger('researchers', 'trg_researchers_role_exclusivity');
SELECT has_trigger('department_administrators', 'trg_dept_admins_role_exclusivity');

SELECT finish();

ROLLBACK;
