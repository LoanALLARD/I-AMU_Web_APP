INSERT INTO places (name, address, city, zip_code) VALUES
    ('Campus de Luminy',       '163 Avenue de Luminy',   'Marseille', '13288'),
    ('Campus Saint-Charles',   '3 Place Victor Hugo',    'Marseille', '13003');

INSERT INTO departments (place_id, name, description) VALUES
    ((SELECT id FROM places WHERE name = 'Campus de Luminy'),
     'Informatique', 'Departement informatique de la FST Luminy'),
    ((SELECT id FROM places WHERE name = 'Campus Saint-Charles'),
     'Mathematiques', 'Departement de mathematiques');

INSERT INTO laboratories (code, name, address, email) VALUES
    ('LIS', 'Laboratoire d''Informatique et Systemes',
     '163 Avenue de Luminy', 'contact@lis-lab.fr');

INSERT INTO super_administrators (email, password_hash, first_name, last_name) VALUES
    ('admin@univ-amu.fr', 'CHANGE_ME', 'Admin', 'Principal');

INSERT INTO users (email, password_hash, first_name, last_name, is_active, theme) VALUES
    ('jean.martin@univ-amu.fr',       'CHANGE_ME', 'Jean',     'Martin',    TRUE, 'LIGHT'),
    ('marie.dupont@univ-amu.fr',      'CHANGE_ME', 'Marie',    'Dupont',    TRUE, 'DARK'),
    ('paul.bernard@univ-amu.fr',      'CHANGE_ME', 'Paul',     'Bernard',   TRUE, 'LIGHT'),
    ('sophie.leroy@univ-amu.fr',      'CHANGE_ME', 'Sophie',   'Leroy',     TRUE, 'LIGHT'),
    ('luc.moreau@univ-amu.fr',        'CHANGE_ME', 'Luc',      'Moreau',    TRUE, 'DARK'),
    ('claire.petit@univ-amu.fr',      'CHANGE_ME', 'Claire',   'Petit',     TRUE, 'LIGHT'),
    ('alice.durand@etu.univ-amu.fr',  'CHANGE_ME', 'Alice',    'Durand',    TRUE, 'LIGHT'),
    ('thomas.roux@etu.univ-amu.fr',   'CHANGE_ME', 'Thomas',   'Roux',      TRUE, 'DARK'),
    ('emma.blanc@etu.univ-amu.fr',    'CHANGE_ME', 'Emma',     'Blanc',     TRUE, 'LIGHT'),
    ('hugo.noir@etu.univ-amu.fr',     'CHANGE_ME', 'Hugo',     'Noir',      TRUE, 'LIGHT'),
    ('lea.vert@etu.univ-amu.fr',      'CHANGE_ME', 'Lea',      'Vert',      TRUE, 'DARK'),
    ('nathan.gris@etu.univ-amu.fr',   'CHANGE_ME', 'Nathan',   'Gris',      TRUE, 'LIGHT'),
    ('chercheur1@univ-amu.fr',        'CHANGE_ME', 'Pierre',   'Curie',     TRUE, 'LIGHT'),
    ('chercheur2@univ-amu.fr',        'CHANGE_ME', 'Henri',    'Poincare',  TRUE, 'DARK'),
    ('orphan@univ-amu.fr',            'CHANGE_ME', 'Sans',     'Role',      TRUE, NULL);

INSERT INTO teachers (id, is_specialised, title) VALUES
    ((SELECT id FROM users WHERE email = 'jean.martin@univ-amu.fr'),  TRUE,  'Professeur'),
    ((SELECT id FROM users WHERE email = 'marie.dupont@univ-amu.fr'), FALSE, 'Maitre de conferences'),
    ((SELECT id FROM users WHERE email = 'paul.bernard@univ-amu.fr'), FALSE, 'Maitre de conferences'),
    ((SELECT id FROM users WHERE email = 'sophie.leroy@univ-amu.fr'), TRUE,  'Professeur'),
    ((SELECT id FROM users WHERE email = 'luc.moreau@univ-amu.fr'),   FALSE, 'ATER'),
    ((SELECT id FROM users WHERE email = 'claire.petit@univ-amu.fr'), FALSE, 'Maitre de conferences');

INSERT INTO department_administrators (id, invited_by_id) VALUES
    ((SELECT id FROM users WHERE email = 'jean.martin@univ-amu.fr'),
     (SELECT id FROM super_administrators WHERE email = 'admin@univ-amu.fr')),
    ((SELECT id FROM users WHERE email = 'sophie.leroy@univ-amu.fr'),
     (SELECT id FROM super_administrators WHERE email = 'admin@univ-amu.fr'));

INSERT INTO department_administrator_assignments (department_id, administrator_id) VALUES
    ((SELECT id FROM departments WHERE name = 'Informatique'),
     (SELECT id FROM users WHERE email = 'jean.martin@univ-amu.fr')),
    ((SELECT id FROM departments WHERE name = 'Mathematiques'),
     (SELECT id FROM users WHERE email = 'sophie.leroy@univ-amu.fr'));

INSERT INTO students (id, student_number, year) VALUES
    ((SELECT id FROM users WHERE email = 'alice.durand@etu.univ-amu.fr'),  '21900001', 2),
    ((SELECT id FROM users WHERE email = 'thomas.roux@etu.univ-amu.fr'),   '21900002', 2),
    ((SELECT id FROM users WHERE email = 'emma.blanc@etu.univ-amu.fr'),    '21900003', 1),
    ((SELECT id FROM users WHERE email = 'hugo.noir@etu.univ-amu.fr'),     '21900004', 1),
    ((SELECT id FROM users WHERE email = 'lea.vert@etu.univ-amu.fr'),      '21900005', 3),
    ((SELECT id FROM users WHERE email = 'nathan.gris@etu.univ-amu.fr'),   '21900006', 3);

INSERT INTO researchers (id, approved_by_id, laboratory_id) VALUES
    ((SELECT id FROM users WHERE email = 'chercheur1@univ-amu.fr'),
     (SELECT id FROM super_administrators WHERE email = 'admin@univ-amu.fr'),
     (SELECT id FROM laboratories WHERE code = 'LIS')),
    ((SELECT id FROM users WHERE email = 'chercheur2@univ-amu.fr'),
     (SELECT id FROM super_administrators WHERE email = 'admin@univ-amu.fr'),
     (SELECT id FROM laboratories WHERE code = 'LIS'));

INSERT INTO resources (owner_id, department_id, code, name, description, semester, state) VALUES
    ((SELECT id FROM users WHERE email = 'jean.martin@univ-amu.fr'),
     (SELECT id FROM departments WHERE name = 'Informatique'),
     'INF101', 'Algorithmique',          'Cours fondamental d''algorithmique', 'S1', 'PUBLISHED'),
    ((SELECT id FROM users WHERE email = 'marie.dupont@univ-amu.fr'),
     (SELECT id FROM departments WHERE name = 'Informatique'),
     'INF202', 'Bases de donnees',       'SGBD relationnels et SQL',           'S2', 'PUBLISHED'),
    ((SELECT id FROM users WHERE email = 'paul.bernard@univ-amu.fr'),
     (SELECT id FROM departments WHERE name = 'Informatique'),
     'INF303', 'Programmation Web',      'Architectures web modernes',         'S3', 'DRAFT'),
    ((SELECT id FROM users WHERE email = 'sophie.leroy@univ-amu.fr'),
     (SELECT id FROM departments WHERE name = 'Mathematiques'),
     'MAT101', 'Analyse',                'Analyse reelle, suites et series',   'S1', 'PUBLISHED');

INSERT INTO teacher_resources (teacher_id, resource_id) VALUES
    ((SELECT id FROM users WHERE email = 'luc.moreau@univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF101')),
    ((SELECT id FROM users WHERE email = 'claire.petit@univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF202'));

INSERT INTO student_resources (student_id, resource_id) VALUES
    ((SELECT id FROM users WHERE email = 'alice.durand@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF101')),
    ((SELECT id FROM users WHERE email = 'thomas.roux@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF101')),
    ((SELECT id FROM users WHERE email = 'emma.blanc@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF202')),
    ((SELECT id FROM users WHERE email = 'hugo.noir@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF202')),
    ((SELECT id FROM users WHERE email = 'lea.vert@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'INF303')),
    ((SELECT id FROM users WHERE email = 'nathan.gris@etu.univ-amu.fr'),
     (SELECT id FROM resources WHERE code = 'MAT101'));

INSERT INTO models (department_id, resource_id, name, version, provider,
                    max_tokens, context_window, api_url, adapter, is_shareable) VALUES
    ((SELECT id FROM departments WHERE name = 'Informatique'),
     NULL,
     'llama3', '8b', 'ollama', 4096, 8192,
     'http://host.docker.internal:11434', 'ollama', TRUE),
    (NULL,
     (SELECT id FROM resources WHERE code = 'INF202'),
     'mistral', '7b', 'ollama', 4096, 8192,
     'http://host.docker.internal:11434', 'ollama', FALSE);

INSERT INTO model_department_accesses (model_id, department_id) VALUES
    ((SELECT id FROM models WHERE name = 'llama3'),
     (SELECT id FROM departments WHERE name = 'Mathematiques'));

INSERT INTO sessions (resource_id, name, status, starts_at, ends_at, type,
                      max_input_size, instructions) VALUES
    ((SELECT id FROM resources WHERE code = 'INF101'),
     'TP Algo - brouillon',         'DRAFT',
     NULL, NULL, 'LAB',
     2000, 'Brouillon de TP, pas encore publie.'),

    ((SELECT id FROM resources WHERE code = 'INF101'),
     'TP Algo - seance 1',          'SCHEDULED',
     NOW() + INTERVAL '2 days', NOW() + INTERVAL '2 days 2 hours', 'LAB',
     2000, 'Premier TP: tri et complexite.'),

    ((SELECT id FROM resources WHERE code = 'INF202'),
     'Examen BDD - blanc',          'SCHEDULED',
     NOW() + INTERVAL '7 days', NOW() + INTERVAL '7 days 3 hours', 'EXAM',
     500,  'Examen blanc. Acces limite, post-prompt strict.'),

    ((SELECT id FROM resources WHERE code = 'INF202'),
     'TD BDD - en cours',           'ACTIVE',
     NOW() - INTERVAL '1 hour', NOW() + INTERVAL '1 hour', 'TUTORIAL',
     1500, 'TD interactif sur les jointures.'),

    ((SELECT id FROM resources WHERE code = 'INF101'),
     'TP Algo - archive',           'ENDED',
     NOW() - INTERVAL '14 days', NOW() - INTERVAL '14 days' + INTERVAL '2 hours', 'LAB',
     2000, 'TP termine. Code d''acces conserve pour traces.'),

    ((SELECT id FROM resources WHERE code = 'MAT101'),
     'CM Analyse - annule',         'CANCELLED',
     NULL, NULL, 'FREE_STUDY',
     NULL, 'Session annulee suite au changement de planning.');

UPDATE sessions
   SET closed_at = NOW() - INTERVAL '14 days' + INTERVAL '2 hours'
 WHERE name = 'TP Algo - archive';

INSERT INTO session_models (model_id, session_id) VALUES
    ((SELECT id FROM models WHERE name = 'llama3'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - seance 1')),
    ((SELECT id FROM models WHERE name = 'mistral'),
     (SELECT id FROM sessions WHERE name = 'Examen BDD - blanc')),
    ((SELECT id FROM models WHERE name = 'mistral'),
     (SELECT id FROM sessions WHERE name = 'TD BDD - en cours')),
    ((SELECT id FROM models WHERE name = 'llama3'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - archive'));

INSERT INTO enrollments (student_id, session_id) VALUES
    ((SELECT id FROM users WHERE email = 'alice.durand@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - seance 1')),
    ((SELECT id FROM users WHERE email = 'thomas.roux@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - seance 1')),
    ((SELECT id FROM users WHERE email = 'emma.blanc@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TD BDD - en cours')),
    ((SELECT id FROM users WHERE email = 'hugo.noir@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TD BDD - en cours')),
    ((SELECT id FROM users WHERE email = 'emma.blanc@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'Examen BDD - blanc')),
    ((SELECT id FROM users WHERE email = 'alice.durand@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - archive'));

INSERT INTO researcher_authorizations (researcher_id, department_id, authorized_by_id) VALUES
    ((SELECT id FROM users WHERE email = 'chercheur1@univ-amu.fr'),
     (SELECT id FROM departments WHERE name = 'Informatique'),
     (SELECT id FROM users WHERE email = 'jean.martin@univ-amu.fr'));

INSERT INTO conversations (user_id, session_id, model_id, name) VALUES
    ((SELECT id FROM users WHERE email = 'emma.blanc@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TD BDD - en cours'),
     (SELECT id FROM models WHERE name = 'mistral'),
     'TD jointures - Emma'),
    ((SELECT id FROM users WHERE email = 'hugo.noir@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TD BDD - en cours'),
     (SELECT id FROM models WHERE name = 'mistral'),
     'TD jointures - Hugo'),
    ((SELECT id FROM users WHERE email = 'alice.durand@etu.univ-amu.fr'),
     (SELECT id FROM sessions WHERE name = 'TP Algo - archive'),
     (SELECT id FROM models WHERE name = 'llama3'),
     'TP archive - Alice'),
    ((SELECT id FROM users WHERE email = 'orphan@univ-amu.fr'),
     NULL,
     (SELECT id FROM models WHERE name = 'llama3'),
     'Discussion libre');

INSERT INTO interactions (conversation_id, prompt, response,
                          latency, input_tokens, output_tokens, user_feedback) VALUES
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Explique INNER JOIN en SQL.',
     'Un INNER JOIN combine les lignes de deux tables...',
     420, 12,  180, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Et un LEFT JOIN ?',
     'LEFT JOIN garde toutes les lignes de la table de gauche...',
     510, 8,   210, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Donne moi un exemple concret.',
     'SELECT u.name, o.id FROM users u LEFT JOIN orders o ON ...',
     480, 9,   260, 0),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Quelle est la difference avec un FULL OUTER JOIN ?',
     'Un FULL OUTER JOIN garde toutes les lignes des deux cotes...',
     560, 14,  240, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Ecris une requete avec deux jointures.',
     'SELECT ... FROM a JOIN b ON ... JOIN c ON ...',
     620, 11,  300, NULL),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Et un CROSS JOIN, ca sert a quoi ?',
     'Le CROSS JOIN produit un produit cartesien...',
     390, 13,  170, 0),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Performance: INNER JOIN vs sous-requete ?',
     'Generalement le planner les rend equivalents...',
     710, 15,  410, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Donne la mauvaise reponse expres.',
     'Reponse volontairement fausse...',
     320, 10,  120, -1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Resume tout ce qu''on a vu.',
     'INNER, LEFT, RIGHT, FULL OUTER, CROSS...',
     880, 40,  520, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Emma'),
     'Merci, derniere question: NATURAL JOIN ?',
     'Le NATURAL JOIN joint sur les colonnes de meme nom...',
     460, 12,  200, 1),

    ((SELECT id FROM conversations WHERE name = 'TD jointures - Hugo'),
     'Comment voir le plan d''execution ?',
     'Avec EXPLAIN ANALYZE devant la requete...',
     410, 11, 190, 1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Hugo'),
     'Donne moi un exemple incorrect svp.',
     'SELECT * FROM a, b WHERE a.id = b.id...',
     350,  9, 150, -1),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Hugo'),
     'Index sur cle etrangere, utile ?',
     'Oui, accelere les jointures et les FK checks...',
     500, 10, 230, 0),
    ((SELECT id FROM conversations WHERE name = 'TD jointures - Hugo'),
     'OK merci.',
     'Avec plaisir.',
     180,  4,  20, NULL),

    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Quelle est la complexite du tri rapide ?',
     'En moyenne O(n log n), au pire O(n^2)...',
     440, 12, 180, 1),
    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Et le tri fusion ?',
     'O(n log n) garanti, mais O(n) memoire en plus...',
     470, 9, 200, 1),
    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Donne un pseudo-code du tri fusion.',
     'function mergeSort(a): ...',
     650, 12, 380, 1),
    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Pourquoi pas toujours utiliser le tri fusion ?',
     'Cout memoire et constante plus elevee qu''un tri rapide...',
     520, 13, 260, 0),
    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Tri par tas, complexite ?',
     'O(n log n) dans tous les cas, en place...',
     410, 8, 170, 1),
    ((SELECT id FROM conversations WHERE name = 'TP archive - Alice'),
     'Resume pour la fiche de revisions.',
     'Quicksort, mergesort, heapsort: tableau recapitulatif...',
     780, 20, 460, 1),

    ((SELECT id FROM conversations WHERE name = 'Discussion libre'),
     'Salut, tu fais quoi ?',
     'Je suis un assistant, je reponds a tes questions.',
     220, 6, 60, 0),
    ((SELECT id FROM conversations WHERE name = 'Discussion libre'),
     'Recommande moi un livre sur l''algorithmique.',
     'Introduction to Algorithms (CLRS) reste la reference...',
     540, 9, 250, 1),
    ((SELECT id FROM conversations WHERE name = 'Discussion libre'),
     'Merci.',
     'Avec plaisir.',
     150, 3, 15, NULL);

INSERT INTO users (email, password_hash, first_name, last_name, consent_version) VALUES
    ('evan@gmail.com', '218937801', 'atherly', 'evan', 'v1');
INSERT INTO teachers (id, title) VALUES
    ((SELECT id FROM users WHERE email = 'evan@gmail.com'), 'dev_Evan');
INSERT INTO places (name, address, city, zip_code) VALUES
    ('IUT Aix', 'site gaston berger', 'Aix-en-Pce', '101010');
INSERT INTO departments (place_id, name, description) VALUES
    ((SELECT id FROM places WHERE name = 'IUT Aix'),
     'departement informatique', 'departement de dev logiciel');
INSERT INTO resources (owner_id, department_id, code, name, description, semester) VALUES
    ((SELECT id FROM users WHERE email = 'evan@gmail.com'),
     (SELECT id FROM departments WHERE name = 'departement informatique'),
     'code', 'dev', 'ressources pour le dev de l outils', 's3');
INSERT INTO models (department_id, resource_id, name, version, provider, max_tokens, context_window, api_url, adapter) VALUES
    ((SELECT id FROM departments WHERE name = 'departement informatique'),
     NULL, 'llama3.2:1b', 'V1', 'meta', 100000, 128000,
     'http://i-amu_web_app-ollama2-1:11434/api/generate', 'ollama');
INSERT INTO sessions (resource_id, name) VALUES
    ((SELECT id FROM resources WHERE code = 'code'), 'session de dev');
INSERT INTO conversations (user_id, session_id, model_id, name) VALUES
    ((SELECT id FROM users WHERE email = 'evan@gmail.com'),
     (SELECT id FROM sessions WHERE name = 'session de dev'),
     (SELECT id FROM models WHERE name = 'llama3.2:1b'),
     'testconv');
