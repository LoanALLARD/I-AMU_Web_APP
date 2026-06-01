-- Seed data tests.
-- The schema and types are implicitly validated by the other test files
-- (any failure to insert valid data into a missing column / type would
-- crash those tests). The only thing that can silently break without
-- detection is the seed content, so that is what we test here.

BEGIN;

SELECT plan(3);

SELECT results_eq(
    $$SELECT domain, role::TEXT, is_active
      FROM email_domain_configs
      ORDER BY domain$$,
    $$VALUES
        ('etu.univ-amu.fr'::VARCHAR, 'STUDENT', TRUE),
        ('univ-amu.fr'::VARCHAR,     'TEACHER', TRUE)$$,
    'Default email domain seeds are present with expected role and active flag'
);

-- The two seeded domains must be the only ones at boot.
SELECT results_eq(
    'SELECT COUNT(*)::INT FROM email_domain_configs',
    ARRAY[2],
    'Exactly 2 email domain configs are seeded'
);

-- Seeds must not reference an admin (added_by_id NULL) — they are bootstrap data.
SELECT results_eq(
    $$SELECT COUNT(*)::INT FROM email_domain_configs WHERE added_by_id IS NOT NULL$$,
    ARRAY[0],
    'Seeded domain configs have no added_by_id (bootstrap data)'
);

SELECT finish();

ROLLBACK;
