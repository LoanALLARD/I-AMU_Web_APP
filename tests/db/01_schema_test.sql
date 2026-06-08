-- Schema structure tests.
-- Only covers what would NOT break loudly elsewhere: ENUM label sets (a
-- dropped/renamed value passes other tests but breaks the app) and the
-- triggers (our own code). Tables, columns, keys and constraints are left
-- to the behavioural tests, which crash plainly if any of them disappear.

BEGIN;

SELECT plan(16);

-- ENUM types must exist with exactly the expected labels.
SELECT has_type('theme_type');
SELECT enum_has_labels('theme_type', ARRAY['LIGHT', 'DARK']);

SELECT has_type('resource_state_type');
SELECT enum_has_labels('resource_state_type', ARRAY['DRAFT', 'PUBLISHED', 'ARCHIVED']);

SELECT has_type('domain_role_type');
SELECT enum_has_labels('domain_role_type', ARRAY['STUDENT', 'TEACHER', 'RESEARCHER']);

SELECT has_type('session_type');
SELECT enum_has_labels('session_type', ARRAY['EXAM', 'TUTORIAL', 'LAB', 'FREE_STUDY']);

SELECT has_type('session_status_type');
SELECT enum_has_labels('session_status_type', ARRAY['DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED']);

-- Triggers must be wired to their tables.
SELECT has_trigger('sessions', 'trg_generate_session_access_code');
SELECT has_trigger('students', 'trg_students_role_exclusivity');
SELECT has_trigger('teachers', 'trg_teachers_role_exclusivity');
SELECT has_trigger('researchers', 'trg_researchers_role_exclusivity');
SELECT has_trigger('department_administrators', 'trg_dept_admins_role_exclusivity');
SELECT has_trigger('model_resource_accesses', 'trg_model_resource_access_same_dept');

SELECT finish();

ROLLBACK;
