-- ============================================================
-- I-AMU - Script de création de la base de données
-- PostgreSQL
-- Basé sur le MCD du rapport d'analyse préliminaire
-- ============================================================

BEGIN;

-- ============================================================
-- Types ENUM
-- ============================================================
CREATE TYPE session_type AS ENUM ('EXAM', 'TP', 'SANDBOX');
CREATE TYPE resource_state AS ENUM ('DRAFT', 'PUBLISHED', 'ARCHIVED');
CREATE TYPE conversation_type AS ENUM ('FREE', 'COURSE', 'EXAM');

-- ============================================================
-- Tables principales
-- ============================================================

-- PLACE
CREATE TABLE place (
    place_id    SERIAL PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    address     VARCHAR(255),
    city        VARCHAR(100),
    zip_code    VARCHAR(10)
);

-- DEPARTMENT
CREATE TABLE department (
    department_id   SERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    place_id        INT REFERENCES place(place_id) ON DELETE SET NULL
);

-- USER (table de base pour tous les utilisateurs)
CREATE TABLE "user" (
    user_id         SERIAL PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login      TIMESTAMP,
    is_active       BOOLEAN DEFAULT TRUE,
    gdpr_consent    BOOLEAN DEFAULT FALSE,
    gdpr_consent_at TIMESTAMP
);

-- STUDENT (spécialisation de USER)
CREATE TABLE student (
    user_id         INT PRIMARY KEY REFERENCES "user"(user_id) ON DELETE CASCADE,
    student_number  VARCHAR(50) UNIQUE
);

-- TEACHER (spécialisation de USER)
CREATE TABLE teacher (
    user_id         INT PRIMARY KEY REFERENCES "user"(user_id) ON DELETE CASCADE,
    is_specialised  BOOLEAN DEFAULT FALSE,
    title           VARCHAR(100)
);

-- RESEARCHER (spécialisation de USER)
CREATE TABLE researcher (
    user_id     INT PRIMARY KEY REFERENCES "user"(user_id) ON DELETE CASCADE,
    laboratory  VARCHAR(255)
);

-- ADMINISTRATOR (spécialisation de USER)
CREATE TABLE administrator (
    user_id         INT PRIMARY KEY REFERENCES "user"(user_id) ON DELETE CASCADE,
    is_super_admin  BOOLEAN DEFAULT FALSE
);

-- MODEL (LLM disponibles)
CREATE TABLE model (
    model_id        SERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    version         VARCHAR(50),
    provider        VARCHAR(100) DEFAULT 'ollama',
    max_tokens      INT,
    context_window  INT,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RESOURCE (cours / matières)
CREATE TABLE resource (
    resource_id     SERIAL PRIMARY KEY,
    code            VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    semester        VARCHAR(20),
    state           resource_state DEFAULT 'DRAFT'
);

-- SESSION (examen, TP ou sandbox)
CREATE TABLE session (
    session_id              SERIAL PRIMARY KEY,
    name                    VARCHAR(255) NOT NULL,
    starts_at               TIMESTAMP,
    ends_at                 TIMESTAMP,
    access_code             VARCHAR(20) UNIQUE,
    system_prompt_override  TEXT,
    max_input_size          INT,
    instructions            TEXT,
    type                    session_type NOT NULL,
    resource_id             INT REFERENCES resource(resource_id) ON DELETE SET NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CONVERSATION
CREATE TABLE conversation (
    conversation_id SERIAL PRIMARY KEY,
    name            VARCHAR(255),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_archived     BOOLEAN DEFAULT FALSE,
    type            conversation_type DEFAULT 'FREE',
    user_id         INT NOT NULL REFERENCES "user"(user_id) ON DELETE CASCADE,
    session_id      INT REFERENCES session(session_id) ON DELETE SET NULL
);

-- INTERACTION (prompts et réponses)
CREATE TABLE interaction (
    prompt_id       SERIAL PRIMARY KEY,
    prompt          TEXT NOT NULL,
    response        TEXT,
    sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    latency         INT,  -- en millisecondes
    input_tokens    INT,
    output_tokens   INT,
    user_feedback   SMALLINT,  -- ex: -1, 0, 1
    conversation_id INT NOT NULL REFERENCES conversation(conversation_id) ON DELETE CASCADE,
    model_id        INT NOT NULL REFERENCES model(model_id) ON DELETE RESTRICT
);

-- ============================================================
-- Tables d'association (relations N:N du MCD)
-- ============================================================

-- Accesses : Student <-> Resource
CREATE TABLE accesses (
    user_id     INT REFERENCES student(user_id) ON DELETE CASCADE,
    resource_id INT REFERENCES resource(resource_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, resource_id)
);

-- TeachesIn : Teacher <-> Resource
CREATE TABLE teaches_in (
    user_id     INT REFERENCES teacher(user_id) ON DELETE CASCADE,
    resource_id INT REFERENCES resource(resource_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, resource_id)
);

-- ManagedBy : Teacher <-> Resource
CREATE TABLE managed_by (
    user_id     INT REFERENCES teacher(user_id) ON DELETE CASCADE,
    resource_id INT REFERENCES resource(resource_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, resource_id)
);

-- Authorizes : Session <-> Model
CREATE TABLE authorizes (
    session_id  INT REFERENCES session(session_id) ON DELETE CASCADE,
    model_id    INT REFERENCES model(model_id) ON DELETE CASCADE,
    PRIMARY KEY (session_id, model_id)
);

-- Owns : Teacher <-> Conversation
CREATE TABLE owns (
    user_id         INT REFERENCES teacher(user_id) ON DELETE CASCADE,
    conversation_id INT REFERENCES conversation(conversation_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, conversation_id)
);

-- BelongsTo : Resource <-> Department
CREATE TABLE belongs_to (
    resource_id     INT REFERENCES resource(resource_id) ON DELETE CASCADE,
    department_id   INT REFERENCES department(department_id) ON DELETE CASCADE,
    PRIMARY KEY (resource_id, department_id)
);

-- IsAffiliatedWith : Researcher <-> Department
CREATE TABLE is_affiliated_with (
    user_id         INT REFERENCES researcher(user_id) ON DELETE CASCADE,
    department_id   INT REFERENCES department(department_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, department_id)
);

-- Administers : Administrator <-> Department
CREATE TABLE administers (
    user_id         INT REFERENCES administrator(user_id) ON DELETE CASCADE,
    department_id   INT REFERENCES department(department_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, department_id)
);

-- IsLinkedTo : Session <-> Place
CREATE TABLE is_linked_to (
    session_id  INT REFERENCES session(session_id) ON DELETE CASCADE,
    place_id    INT REFERENCES place(place_id) ON DELETE CASCADE,
    PRIMARY KEY (session_id, place_id)
);

-- ============================================================
-- Index pour les performances
-- ============================================================
CREATE INDEX idx_user_email ON "user"(email);
CREATE INDEX idx_conversation_user ON conversation(user_id);
CREATE INDEX idx_conversation_session ON conversation(session_id);
CREATE INDEX idx_interaction_conversation ON interaction(conversation_id);
CREATE INDEX idx_interaction_sent_at ON interaction(sent_at);
CREATE INDEX idx_session_access_code ON session(access_code);
CREATE INDEX idx_session_type ON session(type);
CREATE INDEX idx_model_active ON model(is_active);

COMMIT;
