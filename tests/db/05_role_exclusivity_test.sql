-- Role exclusivity enforced by enforce_role_exclusivity(): a user may hold
-- at most one role. Every role pair is forbidden, in both insertion orders.

BEGIN;

SELECT plan(10);

-- ============================================================
-- Fixtures
-- ============================================================

INSERT INTO places (id, name) VALUES (1, 'Campus');
INSERT INTO departments (id, place_id, name) VALUES (1, 1, 'Info');
INSERT INTO laboratories (id, code, name) VALUES (1, 'LIF', 'LIF');
INSERT INTO super_administrators (id, email, password_hash) VALUES (1, 'sa@amu.fr', 'h');

INSERT INTO users (id, email, password_hash) VALUES
    (1, 'u1@amu.fr', 'h'),
    (2, 'u2@amu.fr', 'h'),
    (3, 'u3@amu.fr', 'h'),
    (4, 'u4@amu.fr', 'h'),
    (5, 'u5@amu.fr', 'h'),
    (6, 'u6@amu.fr', 'h'),
    (7, 'u7@amu.fr', 'h'),
    (8, 'u8@amu.fr', 'h');

-- ============================================================
-- Forbidden: teacher combined with department_administrator
-- ============================================================

INSERT INTO teachers (id) VALUES (1);
SELECT throws_ok(
    $$INSERT INTO department_administrators (id, invited_by_id) VALUES (1, 1)$$,
    '23514', NULL,
    'teacher + department_administrator is rejected'
);

-- Reverse insertion order must also be rejected
INSERT INTO department_administrators (id, invited_by_id) VALUES (2, 1);
SELECT throws_ok(
    $$INSERT INTO teachers (id) VALUES (2)$$,
    '23514', NULL,
    'department_administrator + teacher (reverse order) is rejected'
);

-- ============================================================
-- Forbidden: student combined with anything
-- ============================================================

INSERT INTO students (id) VALUES (3);
SELECT throws_ok(
    $$INSERT INTO teachers (id) VALUES (3)$$,
    '23514', NULL,
    'student + teacher is rejected'
);

INSERT INTO teachers (id) VALUES (4);
SELECT throws_ok(
    $$INSERT INTO students (id) VALUES (4)$$,
    '23514', NULL,
    'teacher + student is rejected'
);

INSERT INTO students (id) VALUES (5);
SELECT throws_ok(
    $$INSERT INTO researchers (id, laboratory_id) VALUES (5, 1)$$,
    '23514', NULL,
    'student + researcher is rejected'
);

INSERT INTO students (id) VALUES (6);
SELECT throws_ok(
    $$INSERT INTO department_administrators (id, invited_by_id) VALUES (6, 1)$$,
    '23514', NULL,
    'student + department_administrator is rejected'
);

-- ============================================================
-- Forbidden: researcher combined with anything
-- ============================================================

INSERT INTO researchers (id, laboratory_id) VALUES (7, 1);
SELECT throws_ok(
    $$INSERT INTO teachers (id) VALUES (7)$$,
    '23514', NULL,
    'researcher + teacher is rejected'
);

SELECT throws_ok(
    $$INSERT INTO department_administrators (id, invited_by_id) VALUES (7, 1)$$,
    '23514', NULL,
    'researcher + department_administrator is rejected'
);

INSERT INTO teachers (id) VALUES (8);
SELECT throws_ok(
    $$INSERT INTO researchers (id, laboratory_id) VALUES (8, 1)$$,
    '23514', NULL,
    'teacher + researcher is rejected'
);

-- User 2 is already a department_administrator; adding researcher must fail.
SELECT throws_ok(
    $$INSERT INTO researchers (id, laboratory_id) VALUES (2, 1)$$,
    '23514', NULL,
    'department_administrator + researcher is rejected'
);

SELECT finish();

ROLLBACK;
