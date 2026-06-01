-- Default email domain configurations.
INSERT INTO email_domain_configs (domain, role, is_active) VALUES
    ('etu.univ-amu.fr', 'STUDENT', TRUE),
    ('univ-amu.fr', 'TEACHER', TRUE);

-- INSERT SOME TESTS VALUES

-- USERS
INSERT into users (id, email, password_hash, first_name,last_name,consent_version) VALUES (24,'evan@gmail.com','218937801','atherly','evan','v1');
-- TEACHERS
INSERT into teachers (id,title) VALUES (24,'dev_Evan');
-- PLACES
INSERT into places (id, name, address, city, zip_code) VALUES (2,'IUT Aix','site gaston berger','Aix-en-Pce','101010');
-- DEPARTMENTS
INSERT into departments(id, place_id,name, description) VALUES (12,2,'departement informatique','departement de dev logiciel');
-- RESOURCES
INSERT into resources (id,owner_id,department_id,code,name,description,semester) VALUES (1,24,12,'code','dev','ressources pour le dev de l outils','s3');
-- MODELS
INSERT into models (id,department_id,resource_id,name,version,provider,max_tokens,context_window,api_url,adapter)VALUES (1,12,NULL,'llama3.2:1b','V1','meta',100000,128000,'http://i-amu_web_app-ollama2-1:11434/api/generate','ollama');
-- SESSION
INSERT into sessions (resource_id,name) VALUES (1,'session de dev');
-- CONVERSATION
INSERT into conversations (user_id,session_id,name) VALUES (24,1,'testconv');

