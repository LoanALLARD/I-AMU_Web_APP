# Mémoire projet — I-AMU

> Ce fichier est lu automatiquement à chaque session Claude Code sur ce
> projet. Il sert de **contexte commun** : où sont les choses, comment
> on travaille, et quels documents consulter en priorité.

---

## 1. Le projet en deux phrases

**I-AMU** est une **application web standalone** qui donne aux étudiants
un accès **encadré** à un LLM local (Ollama), avec traçabilité
pédagogique pour les enseignants et export pour la recherche (pas
d'anonymisation côté plateforme — cf. spec 06). PHP 8.1+ /
PostgreSQL 14 / Ollama / Docker. Authentification autonome via
email universitaire (domaines paramétrables en config).

---

## 2. Documents à lire en premier

| Document | Quand le consulter |
|---|---|
| [`documentation/app_architecture.md`](../documentation/app_architecture.md) | **Toujours**, avant de toucher au code. Définit les couches (Core / Domain / Application / Infrastructure / Http), les règles de dépendance, les patterns. |
| [`specs/README.md`](../specs/README.md) | Quand on attaque une nouvelle feature : trouver la spec correspondante. |
| [`specs/00-foundations.md`](../specs/00-foundations.md) → [`05-admin-research.md`](../specs/05-admin-research.md) | Spec détaillée par périmètre fonctionnel. |
| [`README.md`](../README.md) | Vue d'ensemble produit et infrastructure. |

---

## 3. État courant

- **Branche par défaut** : `main`
- **Branche de dev** : `dev` (presque vierge — point de départ de la
  réécriture)
- **Branche de référence** : `poc` (POC complet, sert de bibliothèque
  d'implémentation à reprendre proprement)
- **Branche structure** : reservée à des essais d'org de dossiers

Pour consulter un fichier du POC sans le récupérer :
```bash
git show poc:app/controllers/SessionController.php
```

---

## 4. Stack technique

| Couche       | Techno                                  |
|--------------|-----------------------------------------|
| Langage      | PHP 8.1+ (typed properties, enums, `readonly`) |
| DB           | PostgreSQL 14 (Docker)                  |
| LLM          | Ollama natif (host), accédé via `host.docker.internal` |
| Email dev    | maildev (intercepte tout, port 1080)    |
| Admin DB     | Adminer (port 8081)                     |
| Web          | Apache 2.4 + mod_rewrite (Docker)       |
| Front        | Vanilla JS + marked.js (markdown) + highlight.js (code) |
| Tests        | PHPUnit 10 (à venir)                    |
| Autoloader   | Maison (`app/autoload.php`) — pas celui de Composer |

---

## 5. Conventions clés

### Couches et règles de dépendance

```
Http  →  Application  →  Domain  ←  Infrastructure
                              ↑          ↑
                              └─ Core ───┘
```

- **Domain** : zéro `use` hors `App\Domain\*` ou natif PHP.
- **Infrastructure** : implémente les interfaces du Domain.
- **Http** : reçoit des **interfaces** par constructeur, pas de `new` direct.
- **Aucun singleton** (`getInstance`) dans le code métier.

### Nommage

| Type | Suffixe |
|---|---|
| Interface | `*Interface` (`SessionRepositoryInterface`) |
| Implémentation PDO | `Pdo*Repository` |
| Use-case applicatif | `*Service` avec une méthode `execute()` |
| Controller HTTP | `*Controller` |
| DTO d'entrée | `*Request` |
| DTO de sortie pour vue | `*View` ou `*ViewModel` |
| Exception métier | `*Exception` |

### Style de code (langue + casse)

- **Commentaires en anglais.** Toujours. Y compris les docblocks
  PHPDoc, les TODO, les FIXME, les annotations inline.
  - ✅ `// Hydrate user roles from join tables.`
  - ❌ `// Hydrate les rôles de l'utilisateur depuis les tables de jointure.`

- **Identifiants (classes, méthodes, propriétés, variables) en anglais
  et `camelCase`** — PSR-12 standard.
  - Classes : `PascalCase` (`SessionRepositoryInterface`,
    `OllamaLlmProvider`).
  - Méthodes et propriétés : `camelCase`
    (`findById`, `generateUniqueAccessCode`, `$startsAt`).
  - Constantes de classe : `UPPER_SNAKE_CASE`
    (`STATUS_DRAFT`, `MAX_RETRY_ATTEMPTS`).
  - ✅ `public function findStudentByEmail(Email $email): ?Student`
  - ❌ `public function trouver_etudiant_par_email(...)`
  - ❌ `public function find_student_by_email(...)` (snake_case)
  - ❌ `public function FindStudentByEmail(...)` (PascalCase pour
    méthode)

- **Colonnes DB en `snake_case` anglais** (déjà le cas dans le schéma) :
  `first_name`, `access_code`, `teacher_flag`. PostgreSQL est
  insensible à la casse pour les identifiants non-quotés, mais on
  garde la casse pour la lisibilité dans les requêtes brutes.

- **Strings UI / messages utilisateur en français** (interface
  pédagogique destinée à AMU). Les flash messages, libellés de
  boutons, titres de vues restent en français. Seuls le **code**, les
  **commentaires** et les **messages de commit** sont en anglais
  (cf. §5 *Commits*).

- **Pas de Hungarian notation, pas de préfixe `_` pour les
  privés** — la visibilité PHP (`private`/`protected`/`public`) suffit.

### Commits

- Format **conventional commits** : `feat(...)`, `fix(...)`, `chore(...)`, `docs(...)`, `refactor(...)`, `test(...)`.
- **Messages en anglais**, à l'impératif présent (Git convention).
  - ✅ `feat(sessions): add post-prompt support to session creation`
  - ✅ `fix(admin): correct vertical alignment of role badges in users table`
  - ❌ `feat(sessions): ajoute le support du post-prompt`
  - ❌ `fix(admin): correction de l'alignement vertical des badges`
- Le **body** du commit (multi-lignes) est aussi en anglais, et peut
  expliquer le *pourquoi* (pas seulement le *quoi*).
- **Sans co-auteur** par défaut (préférence du mainteneur).
- Un commit = un slice cohérent (peut être plusieurs par spec).
- Le scope correspond à la spec touchée : `feat(auth)`, `feat(sessions)`,
  `feat(chat)`, `feat(supervise)`, `feat(admin)`, `feat(research)`,
  `feat(rgpd)`, `feat(core)`.

---

## 6. Commandes utiles

### Docker
```bash
docker compose up -d --build     # premier démarrage
docker compose ps                # état
docker compose logs -f app       # logs Apache+PHP
docker compose exec app bash     # shell dans le container
docker compose down              # stop (garde les volumes)
docker compose down -v           # stop + reset DB + maildev
```

### DB
```bash
docker compose exec db psql -U iamu_user -d iamu
# Appliquer une migration :
docker compose exec -T db psql -U iamu_user -d iamu < database/migrations/YYYY-MM-DD-xxx.sql
```

### Lint
```bash
docker compose exec app php -l /var/www/html/app/Core/Application.php
```

### URLs locales

| URL | Quoi |
|---|---|
| http://localhost:8080/  | App |
| http://localhost:8081/  | Adminer (PostgreSQL → server `db`) |
| http://localhost:1080/  | maildev (boîte de réception SMTP de dev) |
| http://localhost:11434/api/tags | Ollama natif |

---

## 7. Comptes / accès

- **Admin par défaut** : `admin` / `Admin` (créé par
  `create_test_admin.php`).
- **Domaines email reconnus** :
  - `@etu.univ-amu.fr` → rôle `student` auto
  - `@univ-amu.fr` → rôle `teacher` auto
  - autres → pas de rôle auto, doit être attribué par un admin.

---

## 8. À faire / à ne pas faire

### À faire
- Lire la spec correspondante **avant** d'écrire du code.
- Référencer le POC : `git show poc:<path>` plutôt que copier-coller à
  l'aveugle.
- Toujours injecter les dépendances par constructeur.
- Toujours typer les arguments et les retours (PHP 8.1+).
- Passer un **ViewModel** aux vues complexes, pas un array PDO.
- **Écrire les commentaires, identifiants ET messages de commit en
  anglais.** Méthodes / propriétés / variables en `camelCase` anglais.
  Commits à l'impératif (`add`, `fix`, `remove`, pas `ajoute` ni `added`).
- Mettre à jour la spec quand on découvre quelque chose pendant
  l'implémentation.

### À ne pas faire
- ❌ `Database::getInstance()` ou `Application::getInstance()` dans le
  métier.
- ❌ `new OllamaLlmProvider()` dans un controller — passer par
  l'interface.
- ❌ `extends Model` en mode ActiveRecord (héritage du POC).
- ❌ Mélanger superglobale (`$_POST`) et `Request` — toujours `Request`.
- ❌ Emojis dans l'UI — utiliser le helper `icon($name)` qui rend des
  SVG Lucide.
- ❌ Hardcoder des modèles LLM dans les seeds. Le tag doit venir
  d'Ollama via la sync.
- ❌ **Commentaires en français dans le code**, **méthodes en
  snake_case / français**, ou **messages de commit en français**
  (cf. §5 Style de code et §5 Commits). Les seules strings en français
  sont les textes UI destinés à l'utilisateur final.

---

## 9. Schéma DB en bref

Tables principales (cf. `database/schema.sql` quand le projet aura été
ré-importé dans dev) :

- `"user"`, `student`, `teacher`, `researcher`, `administrator` (héritage
  vertical pour les rôles).
- `session` (avec `status` enum), `authorizes` (session ↔ model).
- `conversation` (avec `submitted_at`), `interaction` (avec
  `teacher_flag`, `teacher_flag_reason`, `teacher_comment`).
- `model` (les LLM).
- `password_reset` (tokens TTL 1h).
- Tables d'association : `accesses`, `teaches_in`, `managed_by`,
  `is_affiliated_with`, `administers`, `belongs_to`.

---

## 10. Helpers à connaître

| Helper | Usage |
|---|---|
| `icon('check', 'text-success')` | Rend un SVG Lucide inline (cf. `app/Helpers/icons.php`). |
| `csrf_field()` | À ajouter dans tout `<form method="POST">`. |
| `$this->flash('success', 'msg')` | Stocke un flash pour le prochain render. |

---

## 11. Que faire quand je rencontre un cas pas couvert ?

1. Vérifier si une spec couvre le cas → mettre à jour la spec.
2. Si c'est un nouveau périmètre → créer `specs/0X-nom.md` avec le
   template du [`specs/README.md`](../specs/README.md).
3. Si c'est une décision technique transverse → mettre à jour
   `documentation/app_architecture.md` ET ce CLAUDE.md.

---

## 12. Style de réponse attendu

- **En français.**
- Concis mais précis : chiffres, références de fichiers cliquables,
  exemples de code typés.
- Toujours indiquer **pourquoi** une décision a été prise (pas juste
  "j'ai changé X").
- En cas de doute sur les conventions, **consulter le POC** plutôt que
  d'inventer.

---

*Mémoire vivante — toute découverte qui mérite d'être partagée doit
trouver sa place ici.*
