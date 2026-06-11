-- Stress fixtures: generates a large, schema-consistent dataset to load-test
-- the system (lists, dashboards, exports, full-text on prompts/responses).
-- Run AFTER 01_bootstrap.sql and 02_dev_fixtures.sql.
--
-- Volume is driven by the four constants below. Defaults produce roughly:
--   1 000 students, 60 teachers, 30 sessions, ~6 000 conversations,
--   ~120 000 interactions. Bump the multipliers to go heavier.
--
-- Everything created here is tagged 'stress' (emails @stress.univ-amu.fr,
-- resource/session/model codes prefixed 'STRESS') so it can be removed with:
--   DELETE FROM users WHERE email LIKE '%@stress.univ-amu.fr';
--   (cascades clean role rows, conversations, interactions, enrollments...)
--   DELETE FROM resources WHERE code LIKE 'STRESS-%';
--   DELETE FROM models    WHERE name  LIKE 'stress-%';

\set n_students     1000
\set n_teachers     60
\set n_sessions     30
\set msgs_per_conv  20

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- One shared bcrypt hash for every stress account: hashing 1k+ rows with
-- gen_salt('bf') per row is the slow part of the load, and the password is
-- identical anyway. Password for all stress accounts is 'password'.
\set hashed '''$2a$06$z76sPAb4hCccN7.3n9n75e9lSrXqNlaG49H5KMIqbGzUTtiWaMx0O'''

-- ---------------------------------------------------------------------------
-- 1. Bulk teachers (own the stress resources)
-- ---------------------------------------------------------------------------
INSERT INTO users (department_id, email, password_hash, first_name, last_name,
                   is_active, theme, email_verified_at, last_login_at, consent_at, consent_version)
SELECT
    (SELECT id FROM departments WHERE name = 'Informatique'),
    'teacher' || g || '@stress.univ-amu.fr',
    :hashed,
    'Teacher', 'Stress' || g,
    TRUE,
    (ARRAY['LIGHT','DARK','AUTO']::theme_type[])[1 + (g % 3)],
    NOW(),
    NOW() - (g || ' hours')::interval,
    NOW(), '1.0'
FROM generate_series(1, :n_teachers) AS g;

INSERT INTO teachers (id, is_specialised, title)
SELECT u.id, (u.id % 2 = 0), 'Maitre de conferences'
FROM users u
WHERE u.email LIKE 'teacher%@stress.univ-amu.fr';

-- ---------------------------------------------------------------------------
-- 2. Bulk students
-- ---------------------------------------------------------------------------
INSERT INTO users (department_id, email, password_hash, first_name, last_name,
                   is_active, theme, email_verified_at, last_login_at, consent_at, consent_version, research_opposed)
SELECT
    (SELECT id FROM departments WHERE name = 'Informatique'),
    'student' || g || '@stress.univ-amu.fr',
    :hashed,
    'Student', 'Stress' || g,
    TRUE,
    (ARRAY['LIGHT','DARK','AUTO']::theme_type[])[1 + (g % 3)],
    NOW(),
    CASE WHEN g % 7 = 0 THEN NULL ELSE NOW() - (g || ' minutes')::interval END,
    NOW(), '1.0',
    (g % 25 = 0)   -- ~4% object to research use (GDPR), exercises filtering
FROM generate_series(1, :n_students) AS g;

INSERT INTO students (id, student_number, year)
SELECT u.id, '22' || lpad((u.id)::text, 6, '0'), 1 + (u.id % 3)
FROM users u
WHERE u.email LIKE 'student%@stress.univ-amu.fr';

-- ---------------------------------------------------------------------------
-- 3. One stress resource + dept-scoped model the sessions hang off
-- ---------------------------------------------------------------------------
INSERT INTO resources (owner_id, department_id, code, name, description, semester, state)
SELECT
    (SELECT id FROM users WHERE email = 'teacher1@stress.univ-amu.fr'),
    (SELECT id FROM departments WHERE name = 'Informatique'),
    'STRESS-' || g, 'Stress resource ' || g, 'Load-test resource', 'S' || (1 + g % 6), 'PUBLISHED'
FROM generate_series(1, :n_sessions) AS g;

INSERT INTO models (department_id, resource_id, name, size, provider,
                    context_window, api_url, adapter, is_shareable)
VALUES
    ((SELECT id FROM departments WHERE name = 'Informatique'), NULL,
     'stress-model', '8b', 'ollama', 8192,
     'http://host.docker.internal:11434/api/generate', 'ollama', TRUE);

-- ---------------------------------------------------------------------------
-- 4. Sessions (one per stress resource), all states represented
-- ---------------------------------------------------------------------------
INSERT INTO sessions (resource_id, name, status, starts_at, ends_at, type,
                      max_input_size, max_tokens, instructions)
SELECT
    r.id,
    'Stress session ' || r.code,
    (ARRAY['SCHEDULED','ACTIVE','ENDED']::session_status_type[])[1 + (r.id % 3)],
    NOW() - INTERVAL '1 day',
    NOW() + INTERVAL '1 day',
    (ARRAY['EXAM','TUTORIAL','LAB','FREE_STUDY']::session_type[])[1 + (r.id % 4)],
    2000, 4096,
    'Auto-generated stress session.'
FROM resources r
WHERE r.code LIKE 'STRESS-%';

-- Authorise the stress model on every stress session.
INSERT INTO session_models (model_id, session_id)
SELECT (SELECT id FROM models WHERE name = 'stress-model'), s.id
FROM sessions s
WHERE s.name LIKE 'Stress session STRESS-%';

-- ---------------------------------------------------------------------------
-- 5. Enroll every student into ~3 stress sessions (round-robin)
-- ---------------------------------------------------------------------------
INSERT INTO enrollments (student_id, session_id, joined_at, is_active)
SELECT st.id, s.id, NOW() - (st.id % 30 || ' days')::interval, TRUE
FROM students st
JOIN users u ON u.id = st.id AND u.email LIKE 'student%@stress.univ-amu.fr'
JOIN LATERAL (
    SELECT id, row_number() OVER (ORDER BY id) AS rn
    FROM sessions WHERE name LIKE 'Stress session STRESS-%'
) s ON s.rn IN (1 + (st.id % :n_sessions),
                1 + ((st.id + 1) % :n_sessions),
                1 + ((st.id + 2) % :n_sessions))
ON CONFLICT DO NOTHING;

-- ---------------------------------------------------------------------------
-- 6. One conversation per (student, enrolled session)
-- ---------------------------------------------------------------------------
INSERT INTO conversations (user_id, session_id, model_id, name, created_at, is_archived)
SELECT
    e.student_id,
    e.session_id,
    (SELECT id FROM models WHERE name = 'stress-model'),
    'Conv s' || e.student_id || '-' || e.session_id,
    NOW() - (e.student_id % 60 || ' days')::interval,
    (e.student_id % 10 = 0)
FROM enrollments e
JOIN users u ON u.id = e.student_id AND u.email LIKE 'student%@stress.univ-amu.fr';

-- ---------------------------------------------------------------------------
-- 7. Interactions: msgs_per_conv rows per stress conversation.
--    This is the heavy table - the main stress target.
-- ---------------------------------------------------------------------------
INSERT INTO interactions (conversation_id, prompt, response, sent_at,
                          latency, input_tokens, output_tokens, user_feedback, api_metadata)
SELECT
    c.id,
    'Stress prompt #' || g || ' on conversation ' || c.id
        || ' - lorem ipsum dolor sit amet consectetur adipiscing elit.',
    repeat('Generated response chunk. ', 8) || ' (msg ' || g || ')',
    c.created_at + (g || ' minutes')::interval,
    100 + (g * 17 + c.id) % 900,           -- latency 100..1000 ms
    5 + (g * 3) % 60,                       -- input tokens
    20 + (g * 7 + c.id) % 480,              -- output tokens
    (ARRAY[NULL, 1, 0, -1, 1])[1 + (g % 5)],
    jsonb_build_object('model', 'stress-model', 'seq', g, 'finish_reason', 'stop')
FROM conversations c
CROSS JOIN generate_series(1, :msgs_per_conv) AS g
WHERE c.name LIKE 'Conv s%';

-- ---------------------------------------------------------------------------
-- 8. Export audit trail: stress conversations exported by a real researcher
--    (chercheur1 from dev fixtures). Exercises the GDPR audit join.
-- ---------------------------------------------------------------------------
INSERT INTO conversation_exports (conversation_id, researcher_id, ip_address, exported_at)
SELECT
    c.id,
    (SELECT id FROM users WHERE email = 'chercheur1@univ-amu.fr'),
    ('10.0.' || (c.id % 256) || '.' || (c.id % 200 + 1))::inet,
    NOW() - (c.id % 10 || ' hours')::interval
FROM conversations c
WHERE c.name LIKE 'Conv s%' AND c.id % 4 = 0   -- export ~25% of them
ON CONFLICT DO NOTHING;

ANALYZE;

-- Quick volume report.
SELECT 'users'         AS table, count(*) FROM users  WHERE email LIKE '%@stress.univ-amu.fr'
UNION ALL SELECT 'conversations', count(*) FROM conversations WHERE name LIKE 'Conv s%'
UNION ALL SELECT 'interactions',  count(*) FROM interactions i
          JOIN conversations c ON c.id = i.conversation_id WHERE c.name LIKE 'Conv s%';
