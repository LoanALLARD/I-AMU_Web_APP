CREATE TYPE theme_type AS ENUM ('LIGHT', 'DARK', 'AUTO');
CREATE TYPE resource_state_type AS ENUM ('DRAFT', 'PUBLISHED', 'ARCHIVED');
CREATE TYPE domain_role_type AS ENUM ('STUDENT', 'TEACHER', 'RESEARCHER');
CREATE TYPE session_type AS ENUM ('EXAM', 'TUTORIAL', 'LAB', 'FREE_STUDY');
CREATE TYPE session_status_type AS ENUM ('DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED');
CREATE TYPE document_status_type AS ENUM ('PENDING', 'READY', 'FAILED');

CREATE TABLE places (
    id BIGSERIAL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(100),
    city VARCHAR(50),
    zip_code VARCHAR(10),
    display_name VARCHAR(255),
    logo_path VARCHAR(255),
    primary_color VARCHAR(7),
    CONSTRAINT pk_places PRIMARY KEY (id),
    CONSTRAINT ck_places_primary_color CHECK (primary_color IS NULL OR primary_color ~ '^#[0-9a-fA-F]{6}$')
);

CREATE TABLE departments (
    id BIGSERIAL,
    place_id BIGINT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_departments PRIMARY KEY (id),
    CONSTRAINT fk_departments_place FOREIGN KEY (place_id) REFERENCES places (id),
    CONSTRAINT uq_departments_place_name UNIQUE (place_id, name)
);

CREATE TABLE users (
    id BIGSERIAL,
    department_id BIGINT,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    consent_at TIMESTAMPTZ,
    consent_version VARCHAR(50),
    theme theme_type NOT NULL DEFAULT 'AUTO',
    archive_duration_days SMALLINT,
    email_verified_at TIMESTAMPTZ,
    email_verify_token VARCHAR(255),
    research_opposed BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT uq_users_email_verify_token UNIQUE (email_verify_token),
    CONSTRAINT ck_users_archive_duration_days CHECK (archive_duration_days IS NULL OR archive_duration_days > 0)
);

CREATE TABLE super_administrators (
    id BIGSERIAL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ,
    CONSTRAINT pk_super_administrators PRIMARY KEY (id),
    CONSTRAINT uq_super_administrators_email UNIQUE (email)
);

CREATE TABLE teachers (
    id BIGINT,
    is_specialised BOOLEAN NOT NULL DEFAULT FALSE,
    title VARCHAR(50),
    CONSTRAINT pk_teachers PRIMARY KEY (id),
    CONSTRAINT fk_teachers_user FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE students (
    id BIGINT,
    student_number VARCHAR(50),
    year SMALLINT,
    CONSTRAINT pk_students PRIMARY KEY (id),
    CONSTRAINT fk_students_user FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT ck_students_year CHECK (year IS NULL OR year > 0)
);

CREATE TABLE department_administrators (
    id BIGINT,
    invited_by_id BIGINT,
    CONSTRAINT pk_department_administrators PRIMARY KEY (id),
    CONSTRAINT fk_department_administrators_user FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_department_administrators_invited_by FOREIGN KEY (invited_by_id) REFERENCES super_administrators (id) ON DELETE SET NULL
);

CREATE TABLE email_domain_configs (
    id BIGSERIAL,
    added_by_id BIGINT,
    domain VARCHAR(50) NOT NULL,
    role domain_role_type NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_email_domain_configs PRIMARY KEY (id),
    CONSTRAINT fk_email_domain_configs_added_by FOREIGN KEY (added_by_id) REFERENCES super_administrators (id) ON DELETE SET NULL
);

CREATE TABLE laboratories (
    id BIGSERIAL,
    email_domain_config_id BIGINT,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    address VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    CONSTRAINT pk_laboratories PRIMARY KEY (id),
    CONSTRAINT uq_laboratories_code UNIQUE (code),
    CONSTRAINT fk_laboratories_email_domain_config FOREIGN KEY (email_domain_config_id) REFERENCES email_domain_configs (id) ON DELETE SET NULL
);

CREATE TABLE researchers (
    id BIGINT,
    approved_by_id BIGINT,
    laboratory_id BIGINT NOT NULL,
    CONSTRAINT pk_researchers PRIMARY KEY (id),
    CONSTRAINT fk_researchers_user FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_researchers_approved_by FOREIGN KEY (approved_by_id) REFERENCES super_administrators (id) ON DELETE SET NULL,
    CONSTRAINT fk_researchers_laboratory FOREIGN KEY (laboratory_id) REFERENCES laboratories (id)
);

CREATE TABLE resources (
    id BIGSERIAL,
    owner_id BIGINT NOT NULL,
    department_id BIGINT NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    semester VARCHAR(10),
    state resource_state_type NOT NULL DEFAULT 'DRAFT',
    CONSTRAINT pk_resources PRIMARY KEY (id),
    CONSTRAINT fk_resources_owner FOREIGN KEY (owner_id) REFERENCES teachers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_resources_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT
);

CREATE TABLE models (
    id BIGSERIAL,
    department_id BIGINT,
    resource_id BIGINT,
    name VARCHAR(255) NOT NULL,
    size VARCHAR(50),
    provider VARCHAR(255) NOT NULL,
    context_window INTEGER NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    api_url VARCHAR(255) NOT NULL,
    adapter VARCHAR(50) NOT NULL,
    is_shareable BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_models PRIMARY KEY (id),
    CONSTRAINT fk_models_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_models_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT,
    CONSTRAINT ck_models_context_window CHECK (context_window > 0),
    CONSTRAINT ck_models_scope CHECK (
        (resource_id IS NULL AND department_id IS NOT NULL) OR
        (resource_id IS NOT NULL AND department_id IS NULL)
    )
);

CREATE TABLE sessions (
    id BIGSERIAL,
    resource_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    status session_status_type NOT NULL DEFAULT 'DRAFT',
    starts_at TIMESTAMPTZ,
    ends_at TIMESTAMPTZ,
    closed_at TIMESTAMPTZ,
    access_code CHAR(6),
    pre_prompt_override TEXT,
    post_prompt_override TEXT,
    max_input_size INTEGER,
    max_tokens INTEGER,
    instructions TEXT,
    type session_type,
    documents_max_bytes INTEGER,
    CONSTRAINT pk_sessions PRIMARY KEY (id),
    CONSTRAINT fk_sessions_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT,
    CONSTRAINT uq_sessions_access_code UNIQUE (access_code),
    CONSTRAINT ck_sessions_access_code CHECK (access_code IS NULL OR access_code ~ '^[A-Z0-9]{6}$'),
    CONSTRAINT ck_sessions_dates CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at),
    CONSTRAINT ck_sessions_closed_at CHECK (closed_at IS NULL OR starts_at IS NULL OR closed_at >= starts_at),
    CONSTRAINT ck_sessions_max_input_size CHECK (max_input_size IS NULL OR max_input_size > 0),
    CONSTRAINT ck_sessions_max_tokens CHECK (max_tokens IS NULL OR max_tokens > 0),
    CONSTRAINT ck_sessions_documents_max_bytes CHECK (documents_max_bytes IS NULL OR (documents_max_bytes > 0 AND documents_max_bytes <= 10485760))
);

-- Reference list of file formats a session may authorise for student imports.
CREATE TABLE file_formats (
    id BIGSERIAL,
    name VARCHAR(50) NOT NULL,
    CONSTRAINT pk_file_formats PRIMARY KEY (id),
    CONSTRAINT uq_file_formats_name UNIQUE (name)
);

INSERT INTO file_formats (name) VALUES ('pdf'), ('md'), ('txt');

-- Which file formats a session authorises for student document imports
-- (M:N). No row for a session means imports are disabled for it.
CREATE TABLE session_file_formats (
    session_id BIGINT NOT NULL,
    file_format_id BIGINT NOT NULL,
    CONSTRAINT pk_session_file_formats PRIMARY KEY (session_id, file_format_id),
    CONSTRAINT fk_session_file_formats_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_session_file_formats_format FOREIGN KEY (file_format_id) REFERENCES file_formats (id) ON DELETE CASCADE
);

CREATE TABLE conversations (
    id BIGSERIAL,
    user_id BIGINT NOT NULL,
    session_id BIGINT,
    model_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_conversations PRIMARY KEY (id),
    CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversations_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversations_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE RESTRICT
);

CREATE TABLE interactions (
    id BIGSERIAL,
    conversation_id BIGINT NOT NULL,
    prompt TEXT NOT NULL,
    response TEXT,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    latency INTEGER,
    input_tokens INTEGER,
    output_tokens INTEGER,
    user_feedback SMALLINT,
    api_metadata JSONB,
    CONSTRAINT pk_interactions PRIMARY KEY (id),
    CONSTRAINT fk_interactions_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT ck_interactions_user_feedback CHECK (user_feedback IS NULL OR user_feedback IN (-1, 0, 1)),
    CONSTRAINT ck_interactions_input_tokens CHECK (input_tokens IS NULL OR input_tokens > 0),
    CONSTRAINT ck_interactions_output_tokens CHECK (output_tokens IS NULL OR output_tokens >= 0),
    CONSTRAINT ck_interactions_latency CHECK (latency IS NULL OR latency >= 0)
);

CREATE TABLE conversation_exports (
    conversation_id BIGINT,
    researcher_id BIGINT,
    ip_address INET NOT NULL,
    exported_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_conversation_exports PRIMARY KEY (conversation_id, researcher_id),
    CONSTRAINT fk_conversation_exports_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversation_exports_researcher FOREIGN KEY (researcher_id) REFERENCES researchers (id) ON DELETE RESTRICT
);

CREATE TABLE teacher_resources (
    teacher_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_teacher_resources PRIMARY KEY (teacher_id, resource_id),
    CONSTRAINT fk_teacher_resources_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);

CREATE TABLE student_resources (
    student_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_student_resources PRIMARY KEY (student_id, resource_id),
    CONSTRAINT fk_student_resources_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_student_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);

CREATE TABLE session_models (
    model_id BIGINT,
    session_id BIGINT,
    CONSTRAINT pk_session_models PRIMARY KEY (model_id, session_id),
    CONSTRAINT fk_session_models_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE CASCADE,
    CONSTRAINT fk_session_models_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE RESTRICT
);

CREATE TABLE enrollments (
    student_id BIGINT,
    session_id BIGINT,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_enrollments PRIMARY KEY (student_id, session_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE RESTRICT
);

CREATE TABLE researcher_authorizations (
    researcher_id BIGINT,
    department_id BIGINT NOT NULL,
    authorized_by_id BIGINT,
    request TEXT,
    authorized_at TIMESTAMPTZ,
    rejected_at TIMESTAMPTZ,
    CONSTRAINT pk_researcher_authorizations PRIMARY KEY (researcher_id, department_id),
    CONSTRAINT ck_researcher_authorizations_decision CHECK (authorized_at IS NULL OR rejected_at IS NULL OR authorized_at <> rejected_at),
    CONSTRAINT fk_researcher_authorizations_researcher FOREIGN KEY (researcher_id) REFERENCES researchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_researcher_authorizations_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_researcher_authorizations_authorized_by FOREIGN KEY (authorized_by_id) REFERENCES department_administrators (id) ON DELETE SET NULL
);

-- A teacher's request to be habilitated (teachers.is_specialised), scoped to
-- the teacher's own department via users.department_id.
CREATE TABLE teacher_specialisation_requests (
    teacher_id BIGINT,
    decided_by_id BIGINT,
    request TEXT,
    requested_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    approved_at TIMESTAMPTZ,
    rejected_at TIMESTAMPTZ,
    CONSTRAINT pk_teacher_specialisation_requests PRIMARY KEY (teacher_id),
    CONSTRAINT fk_teacher_specialisation_requests_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE,
    CONSTRAINT fk_teacher_specialisation_requests_decided_by FOREIGN KEY (decided_by_id) REFERENCES department_administrators (id) ON DELETE SET NULL
);

CREATE TABLE model_department_accesses (
    model_id BIGINT,
    department_id BIGINT,
    CONSTRAINT pk_model_department_accesses PRIMARY KEY (model_id, department_id),
    CONSTRAINT fk_model_department_accesses_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE CASCADE,
    CONSTRAINT fk_model_department_accesses_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE
);

-- Resource-scoped model shared with other resources of the same department
-- (rule enforced by trg_model_resource_access_same_dept).
CREATE TABLE model_resource_accesses (
    model_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_model_resource_accesses PRIMARY KEY (model_id, resource_id),
    CONSTRAINT fk_model_resource_accesses_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE CASCADE,
    CONSTRAINT fk_model_resource_accesses_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);
CREATE TABLE documents (
    id BIGSERIAL,
    session_id BIGINT,
    conversation_id BIGINT,
    interaction_id BIGINT,
    uploaded_by_id BIGINT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INTEGER NOT NULL,
    extracted_text TEXT,
    status document_status_type NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_documents PRIMARY KEY (id),
    CONSTRAINT fk_documents_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_interaction FOREIGN KEY (interaction_id) REFERENCES interactions (id) ON DELETE SET NULL,
    CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT ck_documents_scope CHECK (
        (session_id IS NOT NULL AND conversation_id IS NULL) OR
        (session_id IS NULL AND conversation_id IS NOT NULL)
    ),
    CONSTRAINT ck_documents_size CHECK (size_bytes > 0)
);
CREATE INDEX idx_documents_session ON documents (session_id);
CREATE INDEX idx_documents_conversation ON documents (conversation_id);
CREATE INDEX idx_documents_interaction ON documents (interaction_id);
