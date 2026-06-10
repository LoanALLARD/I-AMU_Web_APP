-- FK indexes Postgres does not auto-create, limited to FKs filtered/joined in the app.
CREATE INDEX idx_documents_session ON documents (session_id);
CREATE INDEX idx_documents_conversation ON documents (conversation_id);
CREATE INDEX idx_documents_interaction ON documents (interaction_id);
CREATE INDEX idx_interactions_conversation ON interactions (conversation_id);
CREATE INDEX idx_conversations_user ON conversations (user_id);
CREATE INDEX idx_conversations_session ON conversations (session_id);
CREATE INDEX idx_sessions_resource ON sessions (resource_id);
CREATE INDEX idx_enrollments_session ON enrollments (session_id);
CREATE INDEX idx_resources_owner ON resources (owner_id);
CREATE INDEX idx_resources_department ON resources (department_id);
CREATE INDEX idx_teacher_resources_resource ON teacher_resources (resource_id);
CREATE INDEX idx_users_department ON users (department_id);
