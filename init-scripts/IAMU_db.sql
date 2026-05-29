CREATE TYPE theme_type AS ENUM ('light', 'dark');
CREATE TYPE resource_state AS ENUM ('draft', 'published', 'archived');
CREATE TYPE domain_role AS ENUM ('student', 'teacher');
CREATE TYPE session_type AS ENUM ('exam', 'tutorial', 'lab', 'free_study');

CREATE TABLE places (
    id BIGSERIAL,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(100) NOT NULL,
    city VARCHAR(50) NOT NULL,
    zip_code VARCHAR(10) NOT NULL,
    CONSTRAINT pk_places PRIMARY KEY (id)
);

CREATE TABLE departments (
    id BIGSERIAL,
    place_id BIGINT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    CONSTRAINT pk_departments PRIMARY KEY (id),
    CONSTRAINT fk_departments_place FOREIGN KEY (place_id) REFERENCES places (id)
);

CREATE TABLE users (
    id BIGSERIAL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMPTZ,
    consent_at TIMESTAMPTZ,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    consent_version VARCHAR(20),
    theme theme_type,
    archive_duration SMALLINT,
    CONSTRAINT pk_users PRIMARY KEY (id),
    CONSTRAINT uq_users_email UNIQUE (email)
);

CREATE TABLE email_domain_configs (
    id BIGSERIAL,
    domain VARCHAR(50) NOT NULL,
    role domain_role NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_email_domain_configs PRIMARY KEY (id)
);

CREATE TABLE teachers (
    id BIGINT,
    is_specialised BOOLEAN,
    title VARCHAR(30),
    CONSTRAINT pk_teachers PRIMARY KEY (id),
    CONSTRAINT fk_teachers_user FOREIGN KEY (id) REFERENCES users (id)
);

CREATE TABLE students (
    id BIGINT,
    student_number VARCHAR(20),
    CONSTRAINT pk_students PRIMARY KEY (id),
    CONSTRAINT fk_students_user FOREIGN KEY (id) REFERENCES users (id)
);

CREATE TABLE resources (
    id BIGSERIAL,
    teacher_id BIGINT NOT NULL,
    department_id BIGINT NOT NULL,
    code VARCHAR(20),
    name VARCHAR(50) NOT NULL,
    description TEXT,
    semester VARCHAR(10),
    state resource_state NOT NULL,
    CONSTRAINT pk_resources PRIMARY KEY (id),
    CONSTRAINT fk_resources_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_resources_department FOREIGN KEY (department_id) REFERENCES departments (id)
);

CREATE TABLE models (
    id BIGSERIAL,
    resource_id BIGINT,
    name VARCHAR(255) NOT NULL,
    version VARCHAR(50),
    provider VARCHAR(255) NOT NULL,
    max_tokens INTEGER,
    context_window INTEGER,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT pk_models PRIMARY KEY (id),
    CONSTRAINT fk_models_resource FOREIGN KEY (resource_id) REFERENCES resources (id)
);

CREATE TABLE sessions (
    id BIGSERIAL,
    resource_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    starts_at TIMESTAMPTZ NOT NULL,
    ends_at TIMESTAMPTZ NOT NULL,
    closed_at TIMESTAMPTZ,
    access_code VARCHAR(20),
    system_prompt_override TEXT,
    max_input_size INTEGER,
    instructions TEXT,
    type session_type NOT NULL,
    CONSTRAINT pk_sessions PRIMARY KEY (id),
    CONSTRAINT fk_sessions_resource FOREIGN KEY (resource_id) REFERENCES resources (id)
);

CREATE TABLE administrators (
    id BIGINT,
    is_super_admin BOOLEAN,
    CONSTRAINT pk_administrators PRIMARY KEY (id),
    CONSTRAINT fk_administrators_user FOREIGN KEY (id) REFERENCES users (id)
);

CREATE TABLE conversations (
    id BIGSERIAL,
    user_id BIGINT NOT NULL,
    session_id BIGINT NOT NULL,
    name VARCHAR(255),
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
    sent_at TIMESTAMPTZ NOT NULL,
    latency SMALLINT,
    input_tokens INTEGER,
    output_tokens INTEGER,
    user_feedback SMALLINT,
    CONSTRAINT pk_interactions PRIMARY KEY (id),
    CONSTRAINT fk_interactions_model FOREIGN KEY (model_id) REFERENCES models (id),
    CONSTRAINT fk_interactions_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id)
);

CREATE TABLE researchers (
    id BIGINT,
    admin_id BIGINT NOT NULL,
    laboratory VARCHAR(255),
    authorized_at TIMESTAMPTZ,
    CONSTRAINT pk_researchers PRIMARY KEY (id),
    CONSTRAINT fk_researchers_user FOREIGN KEY (id) REFERENCES users (id),
    CONSTRAINT fk_researchers_administrator FOREIGN KEY (admin_id) REFERENCES administrators (id)
);

CREATE TABLE teacher_resources (
    id BIGSERIAL,
    teacher_id BIGINT NOT NULL,
    resource_id BIGINT NOT NULL,
    CONSTRAINT pk_teacher_resources PRIMARY KEY (id),
    CONSTRAINT fk_teacher_resources_teacher FOREIGN KEY (teacher_id) REFERENCES teachers (id),
    CONSTRAINT fk_teacher_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT uq_teacher_resources_teacher_resource UNIQUE (teacher_id, resource_id)
);

CREATE TABLE student_resources (
    id BIGSERIAL,
    student_id BIGINT NOT NULL,
    resource_id BIGINT NOT NULL,
    CONSTRAINT pk_student_resources PRIMARY KEY (id),
    CONSTRAINT fk_student_resources_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_student_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id),
    CONSTRAINT uq_student_resources_student_resource UNIQUE (student_id, resource_id)
);


CREATE TABLE session_models (
    id BIGSERIAL,
    model_id BIGINT NOT NULL,
    session_id BIGINT NOT NULL,
    CONSTRAINT pk_session_models PRIMARY KEY (id),
    CONSTRAINT fk_session_models_model FOREIGN KEY (model_id) REFERENCES models (id),
    CONSTRAINT fk_session_models_session FOREIGN KEY (session_id) REFERENCES sessions (id),
    CONSTRAINT uq_session_models_model_session UNIQUE (model_id, session_id)
);

CREATE TABLE researcher_departments (
    id BIGSERIAL,
    department_id BIGINT NOT NULL,
    researcher_id BIGINT NOT NULL,
    CONSTRAINT pk_researcher_departments PRIMARY KEY (id),
    CONSTRAINT fk_researcher_departments_department FOREIGN KEY (department_id) REFERENCES departments (id),
    CONSTRAINT fk_researcher_departments_researcher FOREIGN KEY (researcher_id) REFERENCES researchers (id),
    CONSTRAINT uq_researcher_departments_department_researcher UNIQUE (department_id, researcher_id)
);

CREATE TABLE administrator_departments (
    id BIGSERIAL,
    department_id BIGINT NOT NULL,
    administrator_id BIGINT NOT NULL,
    CONSTRAINT pk_administrator_departments PRIMARY KEY (id),
    CONSTRAINT fk_administrator_departments_department FOREIGN KEY (department_id) REFERENCES departments (id),
    CONSTRAINT fk_administrator_departments_administrator FOREIGN KEY (administrator_id) REFERENCES administrators (id),
    CONSTRAINT uq_administrator_departments_department_administrator UNIQUE (department_id, administrator_id)
);

CREATE TABLE enrollments (
    id BIGSERIAL,
    student_id BIGINT NOT NULL,
    session_id BIGINT NOT NULL,
    joined_at TIMESTAMPTZ,
    is_active BOOLEAN,
    CONSTRAINT pk_enrollments PRIMARY KEY (id),
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students (id),
    CONSTRAINT fk_enrollments_session FOREIGN KEY (session_id) REFERENCES sessions (id),
    CONSTRAINT uq_enrollments_student_session UNIQUE (student_id, session_id)
);
