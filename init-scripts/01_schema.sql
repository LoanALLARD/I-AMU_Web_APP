CREATE TYPE theme_type AS ENUM ('LIGHT', 'DARK');
CREATE TYPE resource_state_type AS ENUM ('DRAFT', 'PUBLISHED', 'ARCHIVED');
CREATE TYPE domain_role_type AS ENUM ('STUDENT', 'TEACHER');
CREATE TYPE session_type AS ENUM ('EXAM', 'TUTORIAL', 'LAB', 'FREE_STUDY');
CREATE TYPE session_status_type AS ENUM ('DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED');

CREATE TABLE users (
    id BIGSERIAL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    consent_at TIMESTAMPTZ,
    consent_version VARCHAR(50),
    theme theme_type,
    archive_duration_days SMALLINT,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT ck_users_archive_duration_days CHECK (archive_duration_days IS NULL OR archive_duration_days > 0)
);

CREATE TABLE laboratories (
    id BIGSERIAL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    address VARCHAR(100),
    email VARCHAR(255),
    phone VARCHAR(20),
    website VARCHAR(255),
    CONSTRAINT pk_laboratories PRIMARY KEY (id)
);

CREATE TABLE super_administrators (
    id BIGSERIAL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(100),
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
    CONSTRAINT pk_students PRIMARY KEY (id),
    CONSTRAINT fk_students_user FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE TABLE places (
    id BIGSERIAL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(100),
    city VARCHAR(50),
    zip_code VARCHAR(10),
    CONSTRAINT pk_places PRIMARY KEY (id)
);

CREATE TABLE departments (
    id BIGSERIAL,
    place_id BIGINT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_departments PRIMARY KEY (id),
    CONSTRAINT fk_departments_place FOREIGN KEY (place_id) REFERENCES places (id)
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
    version VARCHAR(50),
    provider VARCHAR(255) NOT NULL,
    max_tokens INTEGER NOT NULL,
    context_window INTEGER NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    api_url VARCHAR(255) NOT NULL,
    adapter VARCHAR(50) NOT NULL,
    is_shareable BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_models PRIMARY KEY (id),
    CONSTRAINT fk_models_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE SET NULL,
    CONSTRAINT fk_models_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT,
    CONSTRAINT ck_models_max_tokens CHECK (max_tokens > 0),
    CONSTRAINT ck_models_context_window CHECK (context_window > 0),
    CONSTRAINT ck_models_shareable CHECK (NOT (resource_id IS NOT NULL AND is_shareable = TRUE)),
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
    instructions TEXT,
    type session_type,
    CONSTRAINT pk_sessions PRIMARY KEY (id),
    CONSTRAINT fk_sessions_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT,
    CONSTRAINT uq_sessions_access_code UNIQUE (access_code),
    CONSTRAINT ck_sessions_access_code CHECK (access_code IS NULL OR access_code ~ '^[A-Z0-9]{6}$'),
    CONSTRAINT ck_sessions_dates CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at),
    CONSTRAINT ck_sessions_closed_at CHECK (closed_at IS NULL OR starts_at IS NULL OR closed_at >= starts_at),
    CONSTRAINT ck_sessions_max_input_size CHECK (max_input_size IS NULL OR max_input_size > 0)
);

CREATE TABLE conversations (
    id BIGSERIAL,
    user_id BIGINT NOT NULL,
    session_id BIGINT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_conversations PRIMARY KEY (id),
    CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_conversations_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE RESTRICT
);

CREATE TABLE interactions (
    id BIGSERIAL,
    model_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    prompt TEXT NOT NULL,
    response TEXT,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    latency INTEGER,
    input_tokens INTEGER,
    output_tokens INTEGER,
    user_feedback SMALLINT,
    CONSTRAINT pk_interactions PRIMARY KEY (id),
    CONSTRAINT fk_interactions_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE RESTRICT,
    CONSTRAINT fk_interactions_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT ck_interactions_user_feedback CHECK (user_feedback IS NULL OR user_feedback IN (-1, 0, 1)),
    CONSTRAINT ck_interactions_input_tokens CHECK (input_tokens IS NULL OR input_tokens > 0),
    CONSTRAINT ck_interactions_output_tokens CHECK (output_tokens IS NULL OR output_tokens >= 0),
    CONSTRAINT ck_interactions_latency CHECK (latency IS NULL OR latency >= 0)
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

CREATE TABLE department_administrator_assignments (
    department_id BIGINT,
    administrator_id BIGINT,
    CONSTRAINT pk_department_administrator_assignments PRIMARY KEY (department_id, administrator_id),
    CONSTRAINT fk_department_administrator_assignments_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE,
    CONSTRAINT fk_department_administrator_assignments_administrator FOREIGN KEY (administrator_id) REFERENCES department_administrators (id) ON DELETE CASCADE
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
    authorized_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_researcher_authorizations PRIMARY KEY (researcher_id, department_id),
    CONSTRAINT fk_researcher_authorizations_researcher FOREIGN KEY (researcher_id) REFERENCES researchers (id) ON DELETE CASCADE,
    CONSTRAINT fk_researcher_authorizations_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE RESTRICT,
    CONSTRAINT fk_researcher_authorizations_authorized_by FOREIGN KEY (authorized_by_id) REFERENCES department_administrators (id) ON DELETE SET NULL
);

CREATE TABLE model_department_accesses (
    model_id BIGINT,
    department_id BIGINT,
    CONSTRAINT pk_model_department_accesses PRIMARY KEY (model_id, department_id),
    CONSTRAINT fk_model_department_accesses_model FOREIGN KEY (model_id) REFERENCES models (id) ON DELETE CASCADE,
    CONSTRAINT fk_model_department_accesses_department FOREIGN KEY (department_id) REFERENCES departments (id) ON DELETE CASCADE
);
