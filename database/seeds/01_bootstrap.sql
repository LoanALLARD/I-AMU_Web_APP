-- Default email domain configurations. Required for registration
-- (AuthService derives the role from the email domain).
CREATE EXTENSION IF NOT EXISTS pgcrypto;

INSERT INTO email_domain_configs (domain, role, is_active) VALUES
    ('etu.univ-amu.fr', 'STUDENT', TRUE),
    ('univ-amu.fr', 'TEACHER', TRUE);
INSERT INTO super_administrators (email, password_hash, first_name, last_name) VALUES
    ('admin@univ-amu.fr', crypt('password', gen_salt('bf')), 'Admin', 'Principal');