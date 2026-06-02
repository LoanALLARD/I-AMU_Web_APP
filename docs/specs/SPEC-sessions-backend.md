# SPEC: sessions-backend — Vertical slice Sessions (cours + examen)

> Créée: 2026-05-29 | MàJ: 2026-06-02 | Branche: `Sessions` | Statut: **MVP enseignant livré — côté étudiant + temps réel à faire**

## État d'avancement (2026-06-02)

> Snapshot avant bascule sur la **spec 01 (auth)**. On reprend les
> Sessions après. Le détail des tâches initiales reste plus bas (cases
> cochées de la Phase 4) ; cette section donne la vérité terrain **après**
> le réalignement de schéma, le rebase sur `ServeurFolder` et
> l'intégration du shell d'authentification universel.

### ✅ Fait

| Domaine | État |
|---|---|
| **Clean Archi complète** | Domain (`Session` entity, `SessionType`, `SessionStatus`, `AccessCode`, `SessionRepositoryInterface`, 6 exceptions) + Application (8 services, 4 DTOs, ports `Clock`/`ModelRead`/`ResourceRead`) + Infrastructure (`PdoSessionRepository`, `PdoModelRepository`, `PdoResourceRepository`, `PdoConnection`, `SystemClock`) + Http (`SessionController` 9 actions, `CreateSessionForm`). |
| **Cycle de vie** | `DRAFT → SCHEDULED → ACTIVE → ENDED` + `CANCELLED`, statut **dérivé** (`computedStatus`) et actions contextuelles (`availableActions`). |
| **CRUD enseignant** | Créer / lister / modifier (avant démarrage) / démarrer / terminer / annuler / dashboard. Vue `create.php` réutilisée pour l'édition (`$mode='edit'`, type + code en lecture seule). |
| **Code d'accès** | 6 chars `[A-Z0-9]`, formaté `XXX-XXX` en UI, génération unique (`generateUniqueAccessCode`), copie presse-papier + overlay plein écran. |
| **Sécurité** | `verifyCsrf()` sur tous les POST, `requireRole('teacher')`, ownership guard (`teacherId === currentUser.id`), requêtes préparées only. |
| **Rejoindre (étudiant)** | `JoinSessionService` : normalise le code, vérifie le statut `ACTIVE`. **Mais** voir « Pas fait » pour la suite du flux. |
| **Schéma réaligné** | Tables **plurielles** (`sessions`, `resources`, `models`…), PK `id`. `teacher_id` **dérivé** via JOIN `resources.owner_id` (plus de colonne directe). Contraintes CHECK `ck_sessions_dates` / `ck_sessions_closed_at` respectées par re-ancrage des timestamps dans l'entity. |
| **Intégration UI** | Pages rendues dans le shell d'auth universel (`Layout/chat.php`) : sidebar masquée hors `/chat`, contenu pleine largeur + scroll OK, marges fluides. |

### ⚠️ Divergences vs décisions Phase 1 (le code fait foi)

Le plan du 29/05 a évolué pendant l'implémentation. **Les décisions ci-dessous, listées plus bas dans ce fichier, sont périmées :**

| Décision initiale (29/05) | Réalité actuelle (code) |
|---|---|
| 2 types `COURSE` / `EXAM` | **4 types** `EXAM` / `TUTORIAL` / `LAB` / `FREE_STUDY` (enum PG `session_type`). |
| 3 limites `max_input_tokens` / `max_output_tokens` / `max_requests_per_student` | **1 seule** colonne `max_input_size`. |
| Schéma singulier (`session`, PK `session_id`, colonne `teacher_id`) | Schéma **pluriel** (`sessions`, PK `id`, `teacher_id` dérivé par JOIN). |

Pré-prompt **et** post-prompt sont bien présents (`pre_prompt_override`, `post_prompt_override`), conforme au plan.

### ❌ Pas fait

| Manque | Détail | Dépend de |
|---|---|---|
| **Vue examen étudiant** | Le verrouillage kraft (`02-examen-modern.jsx`) n'est pas porté. | spec 03 |
| **Flux `join` complet** | `join` redirige avec un flash placeholder, **pas** vers un vrai chat de session. | spec 03 |
| **Compteur temps réel** | User story §2 « combien d'étudiants connectés » : non implémenté (pas de polling/WS). | spec 03 |
| **Preflight réel** | « N modèles dispo / charge serveur X % » est un **placeholder statique**, aucun ping Ollama. | spec 03 |
| **`auto_close`** | Clôture auto vs manuelle : question ouverte **non tranchée**, pas de colonne ni de logique. | — |
| **Liste d'étudiants / « 27 places »** | Pas de table `enrollment`, participation implicite via futur `conversation.user_id`. | spec 03 |
| **QR code** | Bouton QR de la maquette : non (could-have). | — |
| **Tests automatisés** | 0 test PHPUnit. La section Tests de [`02-sessions.md`](./02-sessions.md) reste vide. | — |
| **Seed persistant** | `places` / `departments` / `resources` / `models` ont été créés **à la main en DB** pour le dev — **non rejouables** via une migration versionnée. | — |

### Prochaines étapes Sessions (après spec 01)

1. Figer le seed dans une migration versionnée (`database/seed.sql` rejouable).
2. Trancher `auto_close` et l'intégrer à `computedStatus()`.
3. Câbler `join` → chat de session réel (avec spec 03).
4. Compteur d'étudiants connectés + preflight Ollama réel (spec 03).
5. Tests PHPUnit : Domain (`Session` lifecycle, `AccessCode`) + Application (services avec repo in-memory).

## Contexte

Premier vertical slice de la réécriture I-AMU sur la branche `Sessions`. Objectif : permettre à un enseignant de créer / lister / modifier / démarrer / terminer / annuler une session (cours ou examen), et à un étudiant de la rejoindre avec un code d'accès. Bout en bout : DB → Domain → Application → Infrastructure → Http → Vue PHP + Vanilla JS, en suivant la Clean Archi de [`docs/design/app_architecture.md`](../design/app_architecture.md).

État de départ : branche `Sessions` quasi vide (un seul controller `Login/Accueil/LLM` partiel, autoloader OK pour anciens namespaces, pas de DB, pas de bootstrap câblé). La spec [`02-sessions.md`](./02-sessions.md) sert de cahier des charges ; le POC (`git show poc:app/models/Session.php`) sert de bibliothèque d'algorithmes (code généré, statuts dérivés).

Maquette : [`Downloads/I-AMU (1)/src/screens/03-session-modern.jsx`](../../../../Downloads/I-AMU%20(1)/src/screens/03-session-modern.jsx) (création) + [`02-examen-modern.jsx`](../../../../Downloads/I-AMU%20(1)/src/screens/02-examen-modern.jsx) (examen côté étudiant).

## Décisions tranchées avec l'utilisateur (Phases 1 et 3)

| Question | Décision |
|---|---|
| Types de session | **2 valeurs** : `COURSE`, `EXAM` (fusion TP+SANDBOX dans COURSE) |
| Format `AccessCode` | **6 chars `[A-Z0-9]` stockés**, formatés `XXX-XXX` en UI uniquement (~2,17 milliards de codes) |
| Champs limites | **3 colonnes** `max_input_tokens`, `max_output_tokens`, `max_requests_per_student` |
| Code par session vs par étudiant | **1 code partagé** par session (interprétation actuelle spec) |
| Post-prompt | **Inclus** : colonne `post_prompt_override TEXT` |
| Frontend | **Vues PHP + Vanilla JS preview live** (fidèle maquette) |
| Portage vues | **Maquette React refaite à zéro** en vues PHP (pas de reprise HTML du POC) |
| Auth | **Patch `AuthService` pour lire la vraie DB** (replace hardcode) |
| Codage | **3 blocs validés indépendamment** : A=Fondations, B=Domain+App+Infra, C=Http+Vues+JS+Auth |

## Diagnostic (Phase 1)

| Règle | Statut | Détail | Source |
|---|---|---|---|
| Architecture cible définie | OK | 5 couches Core/Domain/Application/Infra/Http | [`app_architecture.md:36-66`](../design/app_architecture.md#L36) |
| Squelette `App\Domain\*` | KO | Namespace pas dans l'autoloader, dossiers absents | [`app/autoload.php:30-36`](../../app/autoload.php#L30) |
| Bootstrap & config | KO | Fichiers vides | [`app/src/bootstrap.php`](../../app/src/bootstrap.php), [`app/src/Config/config.php`](../../app/src/Config/config.php) |
| Connexion PDO | KO | `app/Data/db.php` vide | [`app/Data/db.php`](../../app/Data/db.php) |
| Schéma SQL | KO | Seulement `test1` factice | [`init-scripts/IAMU_db.sql`](../../init-scripts/IAMU_db.sql) |
| Auth réelle | Partiel | Compte hardcodé `test@etu.univ-amu.fr / azerty123` | [`AuthService.php:14`](../../app/src/Services/AuthService.php#L14) — hors scope cette spec |
| Routes session | KO | Aucune route | [`app/public/index.php`](../../app/public/index.php) |
| POC référence | Disponible | `Session::createSession/start/end/cancel/computedStatus/generateAccessCode` | `git show poc:app/models/Session.php` |
| Maquette React/Babel | À traduire | JSX → vues PHP + vanilla JS | `Downloads/I-AMU (1)/src/screens/03-session-modern.jsx` |

**Cause racine de l'effort** : il faut poser **toute** la fondation Clean Archi sur cette branche, pas seulement la feature Sessions. Le découpage en commits permet de séparer la fondation (DB + bootstrap + autoloader) du vertical slice métier (Domain/Application/Infra/Http Sessions).

## Plan d'actions

### Fondations DB (préalable indispensable)

- [x] **DB1** — Créer la migration initiale du schéma : tables `user` + sous-types (`student`, `teacher`) minimaux, `resource`, `model`, `session`, `authorizes`.
  - Fichier : `database/migrations/2026-05-29-001-foundations-schema.sql`
  - Type : Migration SQL
  - Priorité : Haute (bloquant)
- [x] **DB2** — Créer `init-scripts/IAMU_db.sql` (remplace le `test1` factice).
  - Fichier : `init-scripts/IAMU_db.sql`
  - Type : Bootstrap DB
  - Priorité : Haute
- [x] **DB3** — Seed minimal (1 teacher, 1 student, 1 ressource, 2 modèles).
  - Fichier : `database/seed.sql`
  - Type : Seed
  - Priorité : Moyenne

### Backend — Fondations Clean Archi

- [x] **B1** — Mapper les namespaces `App\Domain\`, `App\Application\`, `App\Infrastructure\`, `App\Http\` dans l'autoloader (+ resync composer.json).
- [x] **B2** — Remplir `app/src/Config/config.php` avec config DB (lue depuis `$_ENV`) + Ollama URL + `.env.example`.
- [x] **B3** — `App\Infrastructure\Persistence\PdoConnection` (bind explicite par type pour bug bool/PostgreSQL).
- [x] **B4** — `App\Application\Ports\ClockInterface` + `App\Infrastructure\Clock\SystemClock`.
- [x] **B5** — `app/src/bootstrap.php` : config → PdoConnection → Clock → helpers → Router + routes legacy.
- [x] **B6** — `app/public/index.php` refactor pour passer par bootstrap.
- [x] **B6bis** — `Core\Router` refactor avec params dynamiques `{name}` → regex capture.
- [x] **Préreq** — Port POC `Csrf.php` + `icons.php` + helper `csrf_field()`.

### Backend — Domain Sessions

- [x] **B7** — VO `SessionType` enum `COURSE`/`EXAM` avec `label()` FR.
- [x] **B8** — VO `SessionStatus` enum 5 valeurs avec `label()`, `badgeClass()`, `isTerminal()`.
- [x] **B9** — VO `AccessCode` (`final readonly`), méthodes `formatted()` (XXX-XXX) + `fromUserInput()` (clean).
- [x] **B10** — Entity `Session` avec `rename`, `reschedule`, `reconfigure`, `start`, `end`, `cancel`, `canBeModified`, `computedStatus`, `availableActions`, `isActive`, `assignId`.
- [x] **B11** — Interface `SessionRepositoryInterface` (findById, findByAccessCode, findAllByTeacher, save, authorizedModelIdsOf, setAuthorizedModels, generateUniqueAccessCode).
- [x] **B12** — 5 exceptions + base abstract `SessionException`.

### Backend — Application Sessions

- [x] **B13** — DTO `CreateSessionRequest` avec 3 limites + post-prompt + accessCode + resourceId.
- [x] **B14** — DTO `UpdateSessionRequest` (type et accessCode immuables).
- [x] **B15** — ViewModel `SessionListView` + factory `fromEntity(Session, ClockInterface)`.
- [x] **B16** — ViewModel `SessionDashboardView` + factory `fromEntity` avec rows authorized models.
- [x] **B16bis** — DTO `ModelMetaView` + Port `Application\Ports\ModelReadRepositoryInterface` (le Domain ne pouvait pas porter cette interface car elle consomme un Application DTO).
- [x] **B17** — `CreateSessionService` : garde >=1 modèle, calcule ends_at, persiste + setAuthorizedModels.
- [x] **B18** — `UpdateSessionService` : threading via mutators Domain (rename/reschedule/reconfigure).
- [x] **B19** — `StartSessionService` (Domain throws si état illégal).
- [x] **B20** — `EndSessionService` idem.
- [x] **B21** — `CancelSessionService` (pas besoin de Clock).
- [x] **B22** — `JoinSessionService` : prend `string $rawCode`, normalise via `AccessCode::fromUserInput`, vérifie computed status ACTIVE.
- [x] **B23** — `ListMySessionsService` map entities → SessionListView.
- [x] **B24** — `GetSessionDashboardService` combine session + modèles authorized.

### Backend — Infrastructure

- [x] **B25** — Abstract `PdoRepository` avec `fetchOne` / `fetchAll`.
- [x] **B26** — `PdoSessionRepository` : hydrate Session entity depuis row, INSERT/UPDATE selon id, transaction sur `setAuthorizedModels`, algo `generateUniqueAccessCode` (loop bin2hex).
- [x] **B27** — `PdoModelRepository` (read-only) : `findAllActive` + `findByIds` avec placeholders nommés.

### Backend — Http

- [x] **B28** — `SessionController` 9 actions (index/create/store/edit/update/start/end/cancel/dashboard/join) avec ownership guard (`$session->teacherId() === currentUser.id`) et `verifyCsrf` sur tous les POST.
- [x] **B29** — Routes session enregistrées dans `bootstrap.php` (10 routes au total).
- [x] **B30** — `CreateSessionForm::fromPost` + `fromPostForUpdate` avec validation format + bornes.

### Frontend — Vues + JS

- [x] **F1** — `layouts/layout.php` avec ModernShell + Navbar (brand, breadcrumb, pill nav, user pill, flash stack).
- [x] **F2** — `pages/session/index.php` table desktop + cards mobile + actions contextuelles (eye/edit/play/square/x-circle) selon `canEdit`/`canStart`/`canEnd`/`canCancel`.
- [x] **F3** — `pages/session/create.php` layout 2 colonnes complet : kind cards, planning, modèles, pré/post prompt, instructions, limites + aside (access code, preview live, preflight).
- [x] **F4** — Pas de fichier `edit.php` séparé : `SessionController::edit()` réutilise `pages/session/create.php` avec `$mode='edit'`, le radio `type` est `disabled` et l'accès code en read-only.
- [x] **F5** — `pages/session/dashboard.php` : header + dashboard-grid (Planning/Limites/Prompts à gauche, Code d'accès + Modèles à droite).
- [x] **F6** — `tokens.css` (208 l. portées de la maquette) + `themes.css` (240 l.) + `sessions.css` (350 l.).
- [x] **F7** — `session-create.js` : kind cards toggle, preview live (name/duration/prompt/tokens), models count + preflight, copy code (clipboard API), fullscreen overlay avec Esc.
- [x] **F8** — `session-join.js` : auto-uppercase + tiret après 3 chars + caret restore.

### Helpers transverses

- [x] **H1** — `Helpers/icons.php` portés du POC (18 icônes Lucide).
- [x] **H2** — `Helpers/csrf.php` exposant `csrf_field()` global + `Controller::verifyCsrf()`.

### Auth (patch)

- [x] **Auth1** — `AuthService` refait : reçoit `PdoConnection` par constructeur, SELECT par email, `password_verify` bcrypt, populate session vars, résout les rôles par lookup dans `teacher`/`student`, `session_regenerate_id` anti-fixation.
- [x] **Auth2** — `LoginController` patché pour recevoir `AuthService` par constructeur (DI propre).
- [x] **Auth3** — Hash bcrypt du seed remplacé par un vrai (l'ancien n'était pas un bcrypt valide).

## Fichiers concernés

### À modifier

| Fichier | Raison | Actions liées |
|---|---|---|
| `app/autoload.php` | Ajouter mapping `App\` | B1 |
| `app/src/bootstrap.php` | Câbler la DI | B5 |
| `app/src/Config/config.php` | Remplir config DB + Ollama | B2 |
| `app/src/Core/Controller.php` | Ajouter `verifyCsrf()`, `requireRole()` | H2 |
| `app/public/index.php` | Charger bootstrap, ajouter routes session | B6, B29 |
| `app/src/Views/layouts/layout.php` | Layout commun shell+navbar | F1 |
| `init-scripts/IAMU_db.sql` | Replacer `test1` par les vraies migrations | DB2 |

### À créer

| Fichier | Raison | Actions liées |
|---|---|---|
| `database/migrations/2026-05-29-001-foundations-schema.sql` | Schéma initial | DB1 |
| `database/seed.sql` | Seed dev | DB3 |
| `app/src/Domain/ValueObjects/SessionType.php` | Enum | B7 |
| `app/src/Domain/ValueObjects/SessionStatus.php` | Enum | B8 |
| `app/src/Domain/ValueObjects/AccessCode.php` | VO | B9 |
| `app/src/Domain/Entities/Session.php` | Entity | B10 |
| `app/src/Domain/Repositories/SessionRepositoryInterface.php` | Interface | B11 |
| `app/src/Domain/Exceptions/*Exception.php` | 5 fichiers d'exceptions | B12 |
| `app/src/Application/Ports/ClockInterface.php` | Port | B4 |
| `app/src/Application/DTOs/CreateSessionRequest.php` | DTO | B13 |
| `app/src/Application/DTOs/UpdateSessionRequest.php` | DTO | B14 |
| `app/src/Application/DTOs/SessionListView.php` | ViewModel | B15 |
| `app/src/Application/DTOs/SessionDashboardView.php` | ViewModel | B16 |
| `app/src/Application/Services/CreateSessionService.php` | Service | B17 |
| `app/src/Application/Services/UpdateSessionService.php` | Service | B18 |
| `app/src/Application/Services/StartSessionService.php` | Service | B19 |
| `app/src/Application/Services/EndSessionService.php` | Service | B20 |
| `app/src/Application/Services/CancelSessionService.php` | Service | B21 |
| `app/src/Application/Services/JoinSessionService.php` | Service | B22 |
| `app/src/Application/Services/ListMySessionsService.php` | Service | B23 |
| `app/src/Application/Services/GetSessionDashboardService.php` | Service | B24 |
| `app/src/Infrastructure/Persistence/PdoConnection.php` | Connexion | B3 |
| `app/src/Infrastructure/Persistence/PdoRepository.php` | Abstract | B25 |
| `app/src/Infrastructure/Persistence/PdoSessionRepository.php` | Repo concret | B26 |
| `app/src/Infrastructure/Persistence/PdoModelRepository.php` | Repo concret | B27 |
| `app/src/Infrastructure/Clock/SystemClock.php` | Clock concret | B4 |
| `app/src/Http/Controllers/SessionController.php` | Controller | B28 |
| `app/src/Http/Forms/CreateSessionForm.php` | Validation HTTP | B30 |
| `app/src/Views/pages/session/index.php` | Vue liste | F2 |
| `app/src/Views/pages/session/create.php` | Vue créer | F3 |
| `app/src/Views/pages/session/edit.php` | Vue éditer | F4 |
| `app/src/Views/pages/session/dashboard.php` | Vue dashboard | F5 |
| `app/public/assets/css/sessions.css` | CSS | F6 |
| `app/public/assets/js/session-create.js` | JS preview live | F7 |
| `app/public/assets/js/session-join.js` | JS join | F8 |
| `app/src/Helpers/icons.php` | Helper SVG | H1 |
| `app/src/Helpers/csrf.php` | Helper CSRF | H2 |

### À réutiliser (modèle existant)

| Fichier:ligne | Élément | Pour quelle action |
|---|---|---|
| `git show poc:app/models/Session.php:158-167` (POC) | Algo `generateAccessCode` (boucle random_bytes) | B26 |
| `git show poc:app/models/Session.php:65-80` (POC) | Algo `start()` (passe ACTIVE + starts_at NOW si absent) | B10 |
| `git show poc:app/models/Session.php:105-122` (POC) | Algo `computedStatus` (DRAFT/SCHEDULED/ACTIVE/ENDED dérivés) | B10 |
| `git show poc:app/models/Session.php:124-145` (POC) | Logique `availableActions` | B10 |
| `Downloads/I-AMU (1)/src/screens/03-session-modern.jsx:64-241` | Layout 2 colonnes, sections "kind/name/schedule/models/prompt/limits" | F3 |
| `Downloads/I-AMU (1)/src/screens/02-examen-modern.jsx` | Palette kraft (`#d9c096`, `#2c1f10`) pour type EXAM | F6 |
| `app/src/Core/Controller.php:23-46` | Méthode `render()` existante | F2-F5 |

## Impacts

- [x] **Multi-tenant** : N/A (I-AMU est single-tenant universitaire).
- [x] **Permissions** : `Session::start/end/update/cancel` réservées à l'enseignant propriétaire (vérif dans `SessionController` via `requireRole('teacher')` + check `teacherId == currentUser.id` au niveau Service).
- [x] **Consumers impactés** : aucun (premier vertical slice, rien à casser).
- [x] **Sécurité** : tous les POST passent par `csrf_field()` + `verifyCsrf()`. Pas de concat SQL : préparées seulement.

## Questions ouvertes (à reposer pendant Phase 4 si besoin)

- **Liste d'étudiants enrôlés** : la maquette montre "Aucune liste d'étudiants — code ouvert" comme état preflight. Pas de table `enrollment` dans cette spec (cohérent POC). On stocke la participation via `conversation.user_id` au moment où l'étudiant rejoint. À reconfirmer si on veut un vrai compte "27 places" comme dans la maquette.
- **Auto-clôture** : la maquette propose `<select>` "à la fin" / "manuel". On garde l'info comme champ `auto_close BOOLEAN DEFAULT true` ? À trancher si la valeur "manuel" doit empêcher la transition automatique `ACTIVE→ENDED` à `now > ends_at`. **Proposition** : ajouter `auto_close BOOLEAN NOT NULL DEFAULT TRUE` et l'utiliser dans `computedStatus()`.
- **QR code** : maquette montre un bouton "QR" à côté du code. Génération QR = lib externe ou endpoint qui sort un SVG. Hors scope cette spec (could-have).
- **Préflight** : "3 modèles disponibles sur Ollama", "Charge serveur 12%" → suppose un ping Ollama. Hors scope cette spec — afficher un placeholder statique au lieu d'appeler Ollama.

## Réutilisation (Phase 3)

### Code à reprendre **tel quel** du POC (just renommer namespace)

| Élément POC | Fichier cible I-AMU | Action | Lignes économisées |
|---|---|---|---|
| `git show poc:app/core/Csrf.php` (44 l.) | `app/src/Core/Csrf.php` | Tel quel, garder `namespace Core;` (notre actuel) | ~45 |
| `git show poc:app/helpers/icons.php` (~150 l. avec ~25 icônes) | `app/src/Helpers/icons.php` | Tel quel | ~150 |
| Algo `generateAccessCode()` POC `Session.php:240-249` | `PdoSessionRepository::generateUniqueAccessCode()` | Boucle `bin2hex(random_bytes(3))` + uppercase | ~10 |
| Algo `computedStatus()` POC `Session.php:148-167` | `Session::computedStatus(DateTimeImmutable $now)` Entity | Logique pure (pas de DB), 19 lignes | ~20 |
| Algo `availableActions()` POC `Session.php:124-145` | `Session::availableActions(DateTimeImmutable $now)` Entity | Logique pure | ~22 |
| Algo `canBeModified()` POC `Session.php:135-145` | `Session::canBeModified(DateTimeImmutable $now)` Entity | Logique pure | ~12 |
| Bind PDO explicite `Database::query()` POC `Database.php:60-80` | `PdoConnection::query()` (utile pour bug bool/PostgreSQL) | À adapter sans singleton | ~20 |

### Vues : portage **depuis la maquette React, pas le POC** (décision utilisateur)

| Vue cible | Source d'inspiration | Notes |
|---|---|---|
| `Views/pages/session/create.php` | `Downloads/I-AMU (1)/src/screens/03-session-modern.jsx` (316 l.) | Layout 2 colonnes (1.6fr/1fr), sections kind/name/schedule/models/prompt/limits, panneau droit avec code en grand + preview + preflight |
| `Views/pages/session/index.php` | À déduire (maquette n'a pas de page liste explicite) | Table desktop + cards mobile + menu 3 points. Reprendre style "hairlines + mono + uppercase labels" de la maquette |
| `Views/pages/session/edit.php` | Réutilise `create.php` | Code en lecture seule, sinon identique |
| `Views/pages/session/dashboard.php` | À déduire | Stats + barre d'actions contextuelles |
| `Views/layouts/layout.php` | `m-shared.jsx` (composants `ModernShell`, `Navbar`) | Navbar avec breadcrumb, indicateur rôle, bouton "mes sessions" en haut à droite |

### CSS : tokens de la maquette refaits à zéro

| Source | Cible | Notes |
|---|---|---|
| `Downloads/I-AMU (1)/src/tokens.css` | `app/public/assets/css/tokens.css` | Variables CSS de la maquette : `--bg`, `--surface`, `--text`, `--text-2`, `--text-3`, `--border`, `--border-2`, `--hair`, fonts Geist + JetBrains Mono |
| `Downloads/I-AMU (1)/src/themes-modern.css` | `app/public/assets/css/themes.css` | Thème clair + sombre |
| (nouveau) | `app/public/assets/css/sessions.css` | Classes spécifiques `.msection`, `.kind-card`, `.kind-card.kraft.active`, `.access-code-display`, `.preflight-list`, `.preview-card` |
| Palette kraft pour EXAM | dans `sessions.css` | `#d9c096` (kraft), `#2c1f10` (kraft-deep), `#c8ad7d` (kraft-soft), `#5a4626` (kraft-sub), `#7a6240` (kraft-dim) — réutilisés pour `02-examen-modern.jsx` mais hors scope (côté étudiant) |

### Code à enrichir (déjà présent sur branche `Sessions`)

| Fichier actuel | Enrichissement | Origine |
|---|---|---|
| `app/src/Core/Controller.php` | Ajouter `requireRole(string)`, `requireAnyRole(array)`, `json()`, `query()`, `renderPartial()`, helper `verifyCsrf()` | POC `app/core/Controller.php:80-115` |
| `app/src/Core/Router.php` | Ajouter support des **params dynamiques** (`/sessions/{id}`) — actuel matche les paths littéraux uniquement | À refactor : extraire `{name}` → regex `[^/]+`, passer params à la callback |
| `app/autoload.php` | Mapper `App\Domain\` `App\Application\` `App\Infrastructure\` `App\Http\` `App\Core\` `App\Helpers\` | B1 (voir plan) |

### Code à **NE PAS** reprendre du POC

| Anti-pattern POC | Pourquoi | Action |
|---|---|---|
| `extends Model` sur `Session` (ActiveRecord) | Couplé DB+métier | Splitter en `Entity` + `Repository` |
| `Database::getInstance()` dans les services | Singleton statique inverse les dépendances | Injecter `PdoConnection` par constructeur |
| `Application::getInstance()` dans `OllamaService`, `Database` | Idem | Injecter config |
| `new OllamaService()` dans `SessionController::create()` (preflight) | Couplage direct adapter | Hors scope cette spec (preflight statique côté UI) |
| Vues qui font `new SessionModel()` (POC `dashboard.php:4`) | Vue qui parle au métier | Précalculer dans `SessionDashboardView` |
| `Conversation::createConversation()` appelé dans `SessionController::join()` | Hors périmètre cette spec | Reporter à spec 03-chat ; pour l'instant, `join` redirige vers `/chat?session={id}` (placeholder) |

### Bilan

- **Réutilisable tel quel** : ~245 lignes (Csrf + icons + 2 algos courts)
- **Réutilisable avec adaptation** (vues + CSS + JS) : ~1100 lignes économisées
- **À écrire neuf** : essentiellement la couche Clean Archi (entities/services/repos/DTOs) — ~1000 lignes
- **Estimation totale révisée** : on tombe de ~2200 lignes à écrire à **~1000 lignes neuves** + ~1300 lignes portées du POC. Le vrai effort est dans le typage et le câblage DI, pas dans la logique métier (déjà éprouvée).

### Découvertes Phase 3

1. **`Core/Router.php` actuel ne supporte pas les params dynamiques** — bloquant pour `/sessions/{id}`. À ajouter en B6bis dans le plan.
2. **`badge-course` / `badge-exam` existent déjà** dans le CSS POC (`style.css:347-348`) → bonne nouvelle pour la décision 2-types.
3. **POC utilise `TP` comme valeur radio** (`create.php` `<input value="TP">`) — il faut remplacer par `COURSE` dans toutes les vues.
4. **Le POC a `gdpr_consent`/`gdpr_consent_at` sur `user`** — utile pour spec 06 mais hors scope ici. À garder dans la migration DB1 pour ne pas avoir à `ALTER TABLE` plus tard.
5. **Bug PostgreSQL connu** (`Database::query()` POC `Database.php:60-80`) : booléen `false` rejeté si pas de `PDO::PARAM_BOOL`. Important pour `PdoConnection::query()`.

## Journal

| Phase | Date | Statut |
|---|---|---|
| Phase 0 — Specs & Doc | 2026-05-29 | Fait |
| Phase 1 — Diagnostic | 2026-05-29 | Validé |
| Phase 2 — Spec | 2026-05-29 | Validé |
| Phase 3 — Réutilisation | 2026-05-29 | Validé |
| Phase 4 — Codage Bloc A (Fondations) | 2026-05-29 | Terminé — Review APPROVE 8/8 |
| Phase 4 — Codage Bloc B (Domain/App/Infra) | 2026-05-29 | Terminé — Review APPROVE 8/8 |
| Phase 4 — Codage Bloc C (Http/Vues/JS) | 2026-05-29 | Terminé — Review APPROVE 8/8 |
| Phase 5 — Vérification finale | 2026-05-29 | OK (curl + Chrome screenshots) |
| Phase 6 — Capitalisation | 2026-05-29 | Fait (cette note) |
| Réalignement schéma (tables plurielles, teacher_id dérivé) | 2026-06-01 | Fait — 4 types, `max_input_size` unique |
| Rebase sur `ServeurFolder` + shell d'auth universel | 2026-06-02 | Fait — sidebar/topbar partagés, pleine largeur + scroll |
| Snapshot fait/pas-fait (cette section « État d'avancement ») | 2026-06-02 | Fait — bascule sur spec 01, reprise Sessions ensuite |
