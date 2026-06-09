-- Default email domain configurations. Required for registration
-- (AuthService derives the role from the email domain). The remaining
-- dev data lives in 02_dev_fixtures.sql, which owns it as a single source.
INSERT INTO email_domain_configs (domain, role, is_active) VALUES
    ('etu.univ-amu.fr', 'STUDENT', TRUE),
    ('univ-amu.fr', 'TEACHER', TRUE);
