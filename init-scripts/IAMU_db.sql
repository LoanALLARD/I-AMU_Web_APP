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
    archive_duration SMALLINT,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_users_email UNIQUE (email)
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
    CONSTRAINT fk_teachers_user FOREIGN KEY (id) REFERENCES users (id)
);

CREATE TABLE students (
    id BIGINT,
    student_number VARCHAR(50),
    CONSTRAINT pk_students PRIMARY KEY (id),
    CONSTRAINT fk_students_user FOREIGN KEY (id) REFERENCES users (id)
);

CREATE TABLE places (
    id BIGSERIAL,
    super_administrator_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(100),
    city VARCHAR(50),
    zip_code VARCHAR(10),
    CONSTRAINT pk_places PRIMARY KEY (id),
    CONSTRAINT fk_places_super_administrator FOREIGN KEY (super_administrator_id) REFERENCES super_administrators (id)
);

CREATE TABLE departments (
    id BIGSERIAL,
    place_id BIGINT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    CONSTRAINT pk_departments PRIMARY KEY (id),
    CONSTRAINT fk_departments_place FOREIGN KEY (place_id) REFERENCES places (id)
);

CREATE TABLE researchers (
    id BIGINT,
    super_administrator_id BIGINT NOT NULL,
    laboratory_id BIGINT NOT NULL,
    CONSTRAINT pk_researchers PRIMARY KEY (id),
    CONSTRAINT fk_researchers_user FOREIGN KEY (id) REFERENCES users (id),
    CONSTRAINT fk_researchers_super_administrator FOREIGN KEY (super_administrator_id) REFERENCES super_administrators (id),
    CONSTRAINT fk_researchers_laboratory FOREIGN KEY (laboratory_id) REFERENCES laboratories (id)
);

CREATE TABLE department_administrators (
    id BIGINT,
    super_administrator_id BIGINT NOT NULL,
    CONSTRAINT pk_department_administrators PRIMARY KEY (id),
    CONSTRAINT fk_department_administrators_user FOREIGN KEY (id) REFERENCES users (id),
    CONSTRAINT fk_department_administrators_super_administrator FOREIGN KEY (super_administrator_id) REFERENCES super_administrators (id)
);

CREATE TABLE email_domain_configs (
    id BIGSERIAL,
    super_administrator_id BIGINT NOT NULL,
    domain VARCHAR(50) NOT NULL,
    role domain_role_type NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_email_domain_configs PRIMARY KEY (id),
    CONSTRAINT fk_email_domain_configs_super_administrator FOREIGN KEY (super_administrator_id) REFERENCES super_administrators (id)
);

CREATE TABLE resources (
    id BIGSERIAL,
    teacher_id BIGINT NOT NULL,
    department_id BIGINT NOT NULL,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    semester VARCHAR(50),
    state resource_state_type NOT NULL DEFAULT 'DRAFT',
    CONSTRAINT pk_resources PRIMARY KEY (id),
    CONSTRAINT fk_resources_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_resources_department FOREIGN KEY (department_id) REFERENCES departments (id)
);

CREATE TABLE models (
    id BIGSERIAL,
    department_id BIGINT NOT NULL,
    resource_id BIGINT,
    name VARCHAR(255) NOT NULL,
    version VARCHAR(255),
    provider VARCHAR(255) NOT NULL,
    max_tokens INTEGER NOT NULL,
    context_window INTEGER NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    api_url VARCHAR(255) NOT NULL,
    adapter VARCHAR(50) NOT NULL,
    is_shareable BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_models PRIMARY KEY (id),
    CONSTRAINT fk_models_department FOREIGN KEY (department_id) REFERENCES departments (id),
    CONSTRAINT fk_models_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT ck_models_max_tokens CHECK (max_tokens > 0),
    CONSTRAINT ck_models_context_window CHECK (context_window > 0),
    CONSTRAINT ck_models_shareable CHECK (NOT (resource_id IS NOT NULL AND is_shareable = TRUE))
);

CREATE TABLE sessions (
    id BIGSERIAL,
    resource_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    status session_status_type NOT NULL DEFAULT 'DRAFT',
    starts_at TIMESTAMPTZ,
    ends_at TIMESTAMPTZ,
    closed_at TIMESTAMPTZ,
    access_code VARCHAR(255),
    pre_prompt_override TEXT,
    post_prompt_override TEXT,
    max_input_size INTEGER,
    instructions TEXT,
    type session_type,
    CONSTRAINT pk_sessions PRIMARY KEY (id),
    CONSTRAINT fk_sessions_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT ck_sessions_dates CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at)
);

CREATE TABLE conversations (
    id BIGSERIAL,
    user_id BIGINT NOT NULL,
    session_id BIGINT,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT pk_conversations PRIMARY KEY (id),
    CONSTRAINT fk_conversations_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_conversations_session FOREIGN KEY (session_id) REFERENCES sessions (id)
);

CREATE TABLE interactions (
    id BIGSERIAL,
    model_id BIGINT NOT NULL,
    conversation_id BIGINT NOT NULL,
    prompt TEXT NOT NULL,
    response TEXT,
    sent_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    latency SMALLINT,
    input_tokens INTEGER,
    output_tokens INTEGER,
    user_feedback SMALLINT,
    CONSTRAINT pk_interactions PRIMARY KEY (id),
    CONSTRAINT fk_interactions_model FOREIGN KEY (model_id) REFERENCES models (id),
    CONSTRAINT fk_interactions_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id),
    CONSTRAINT ck_interactions_user_feedback CHECK (user_feedback IS NULL OR user_feedback IN (-1, 0, 1))
);

CREATE TABLE teacher_resources (
    teacher_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_teacher_resources PRIMARY KEY (teacher_id, resource_id),
    CONSTRAINT fk_teacher_resources_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_teacher_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id)
);

CREATE TABLE student_resources (
    student_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_student_resources PRIMARY KEY (student_id, resource_id),
    CONSTRAINT fk_student_resources_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_student_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id)
);

CREATE TABLE session_models (
    model_id BIGINT,
    session_id BIGINT,
    CONSTRAINT pk_session_models PRIMARY KEY (model_id, session_id),
    CONSTRAINT fk_session_models_model FOREIGN KEY (model_id) REFERENCES models (id),
    CONSTRAINT fk_session_models_session FOREIGN KEY (session_id) REFERENCES sessions (id)
);

CREATE TABLE department_administrator_assignments (
    department_id BIGINT,
    department_administrator_id BIGINT,
    CONSTRAINT pk_department_administrator_assignments PRIMARY KEY (department_id, department_administrator_id),
    CONSTRAINT fk_department_administrator_assignments_department FOREIGN KEY (department_id) REFERENCES departments (id),
    CONSTRAINT fk_department_administrator_assignments_administrator FOREIGN KEY (department_administrator_id) REFERENCES department_administrators (id)
);

CREATE TABLE enrollments (
    student_id BIGINT,
    session_id BIGINT,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_enrollments PRIMARY KEY (student_id, session_id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_enrollments_session FOREIGN KEY (session_id) REFERENCES sessions (id)
);

CREATE TABLE researcher_authorizations (
    department_administrator_id BIGINT,
    researcher_id BIGINT,
    department_id BIGINT NOT NULL,
    authorized_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_researcher_authorizations PRIMARY KEY (department_administrator_id, researcher_id),
    CONSTRAINT fk_researcher_authorizations_administrator FOREIGN KEY (department_administrator_id) REFERENCES department_administrators (id),
    CONSTRAINT fk_researcher_authorizations_researcher FOREIGN KEY (researcher_id) REFERENCES researchers (id),
    CONSTRAINT fk_researcher_authorizations_department FOREIGN KEY (department_id) REFERENCES departments (id)
);

CREATE TABLE model_department_authorizations (
    model_id BIGINT,
    department_id BIGINT,
    CONSTRAINT pk_model_department_authorizations PRIMARY KEY (model_id, department_id),
    CONSTRAINT fk_model_department_authorizations_model FOREIGN KEY (model_id) REFERENCES models (id),
    CONSTRAINT fk_model_department_authorizations_department FOREIGN KEY (department_id) REFERENCES departments (id)
);
