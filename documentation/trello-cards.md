# Cartes Trello — prêtes à l'emploi

> **Comment utiliser ce document** — chaque section `##` correspond à une
> **liste Trello** (alignée sur une spec). Chaque `###` correspond à
> **une carte**. Le titre est tel quel, la description (avec checklist
> Markdown) se colle directement dans la description Trello.
>
> Pour un import en masse : utiliser le power-up *Card Repeater* ou
> coller titre par titre via la fonction "ajouter plusieurs cartes"
> (les checklists devront être saisies à la main, Trello ne les parse
> pas depuis la description).

---

## Conventions de labels

| Label | Couleur suggérée | Sens |
|---|---|---|
| `must` | rouge | Must-have (rapport §2.3.1) |
| `should` | orange | Should-have (rapport §2.3.2) |
| `could` | jaune | Could-have (rapport §2.3.3) |
| `core` | gris | Couche `Core/` |
| `domain` | bleu | Couche `Domain/` |
| `app` | violet | Couche `Application/` |
| `infra` | turquoise | Couche `Infrastructure/` |
| `http` | vert | Couche `Http/` |
| `view` | vert clair | Vues PHP + CSS + JS |
| `db` | noir | Migration / schéma DB |
| `tests` | rose | Tests PHPUnit |
| `rgpd` | bleu marine | Lié RGPD / sécurité |

## Échelle de story points

| Pts | Effort estimé |
|----|---|
| 1  | ≤ 2 h, sans piège |
| 2  | demi-journée |
| 3  | une journée |
| 5  | 2-3 jours |
| 8  | une semaine |
| 13 | trop gros, à découper |

---

## 📋 Liste 1 — `00-foundations`

### [F-01] Autoloader natif + bootstrap + Container

**Labels** : `must` `core` `spec-00`
**Points** : 3

**Description**
Mettre en place le socle de chargement de classes et d'assemblage des
dépendances. Aucune logique métier.

**Checklist**
- [ ] `app/autoload.php` : `spl_autoload_register` pour `App\` et `PHPMailer\PHPMailer\` avec fallback PascalCase ↔ lowercase
- [ ] `app/Core/Container.php` : wrapper minimal `get(class)` / `has(class)`, lance si inconnu
- [ ] `app/bootstrap.php` : instancie PdoConnection, SystemClock, repositories, services, controllers ; retourne le Container
- [ ] `public/index.php` : `require autoload.php` puis `require bootstrap.php` puis `Application::run()`

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.1, §2.2, §2.3

---

### [F-02] Core/Application + chargement config

**Labels** : `must` `core` `spec-00`
**Points** : 2

**Description**
Orchestrateur principal qui démarre la session PHP, charge la config,
peuple le routeur, dispatche la requête.

**Checklist**
- [ ] `app/Core/Application.php` : constructeur(`Container`, `array $config`), méthode `run()`
- [ ] Démarre la session PHP si pas déjà fait
- [ ] Pas de singleton statique (`getInstance` interdit)
- [ ] Charge `config/routes.php` qui peuple un `Router` injecté

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.4

---

### [F-03] Router + dispatch

**Labels** : `must` `core` `spec-00`
**Points** : 3

**Description**
Routeur à table en mémoire avec extraction de paramètres dynamiques
(`{id}`).

**Checklist**
- [ ] `app/Core/Router.php`
- [ ] Méthodes `get(path, controllerClass, methodName)` et `post(...)`
- [ ] Matching avec regex `#^/sessions/(?P<id>[^/]+)$#`
- [ ] `dispatch(Request)` → récupère controller via Container, appelle la méthode avec Request + params extraits
- [ ] 404 → `ErrorController::notFound`
- [ ] Catch global → `ErrorController::serverError`

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.5

---

### [F-04] Request immuable (wrapper superglobales)

**Labels** : `must` `core` `spec-00`
**Points** : 2

**Description**
Plus aucun `$_POST`/`$_GET` direct dans le métier — tout passe par
`Request`.

**Checklist**
- [ ] `app/Core/Request.php` (final, readonly)
- [ ] `Request::fromGlobals()` factory
- [ ] Méthodes `method()`, `path()`, `query()`, `input()`, `all()`, `isJson()`, `jsonBody()`, `header()`
- [ ] Tests unitaires (3-4 cas)

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.6

---

### [F-05] Controller abstrait + helpers (render, redirect, json, flash, auth)

**Labels** : `must` `core` `spec-00`
**Points** : 3

**Description**
Base abstraite pour tous les controllers HTTP. Pas de logique métier.

**Checklist**
- [ ] `app/Core/Controller.php` (abstract)
- [ ] `render(view, data, layout)` avec `extract($data)` + capture buffer
- [ ] `redirect(url)` + `json(payload, status)`
- [ ] `flash(type, message)` (session)
- [ ] `requireAuth()`, `requireRole(role)`, `requireAnyRole(roles)`, `currentUser()`

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.7

---

### [F-06] CSRF protection

**Labels** : `must` `core` `spec-00`
**Points** : 2

**Description**
Jeton de session, vérification dans tous les POST sensibles.

**Checklist**
- [ ] `app/Core/Csrf.php` : `token()`, `verify(Request)`
- [ ] Helper de vue `csrf_field()` rendant `<input type="hidden" name="_csrf" value="…">`
- [ ] `CsrfException` (classe propre)
- [ ] Documentation : tous les controllers POST doivent appeler `verify()`

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.8

---

### [F-07] Validator fluent

**Labels** : `must` `core` `spec-00`
**Points** : 2

**Description**
Validation déclarative légère, pas une lib complète.

**Checklist**
- [ ] `app/Core/Validator.php` (fluent builder)
- [ ] Règles : `required`, `minLength`, `maxLength`, `in(values)`, `email`, `date`, `int`, `min`, `max`
- [ ] Retourne `array $errors`
- [ ] Tests unitaires par règle

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §2.9

---

### [F-08] Healthcheck end-to-end

**Labels** : `must` `core` `tests` `spec-00`
**Points** : 1

**Description**
Critère "spec 00 terminée" : `GET /healthz` répond `{ok:true}`.

**Checklist**
- [ ] Route `GET /healthz` → `HealthController::index`
- [ ] `POST /healthz/echo` avec CSRF renvoie le body reçu
- [ ] Aucun appel à `Database::getInstance()` ou superglobale dans `Core/`

**Référence** : [`specs/00-foundations.md`](../specs/00-foundations.md) §6

---

## 📋 Liste 2 — `01-auth-account`

### [A-01] Domain User + ValueObjects + GdprConsent

**Labels** : `must` `domain` `spec-01`
**Points** : 3

**Description**
Entité User pure (sans persistence) avec ses VOs.

**Checklist**
- [ ] `Domain/Entities/User.php` (verifyPassword, changePassword, renameTo, recordLogin, grantGdprConsent, revokeGdprConsent, setArchiveDays, deactivate)
- [ ] `Domain/ValueObjects/Email.php` (validation `FILTER_VALIDATE_EMAIL`, `domain()`)
- [ ] `Domain/ValueObjects/UserRole.php` (enum 5 valeurs)
- [ ] `Domain/ValueObjects/GdprConsent.php` (granted, grantedAt, revokedAt)
- [ ] Tests unitaires : Email invalide → exception, User::verifyPassword

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §3

---

### [A-02] Repository interfaces (User, PasswordReset)

**Labels** : `must` `domain` `spec-01`
**Points** : 1

**Description**
Contrats pour la couche persistence.

**Checklist**
- [ ] `Domain/Repositories/UserRepositoryInterface.php` (findById, findByEmail, save, rolesOf, attachRole, detachRole, studentNumberOf)
- [ ] `Domain/Repositories/PasswordResetRepositoryInterface.php` (save, findByToken, delete, deleteAllForUser)
- [ ] Exceptions : `UserNotFoundException`, `InvalidCredentialsException`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §3

---

### [A-03] App services : Register, Login, Logout

**Labels** : `must` `app` `spec-01`
**Points** : 3

**Description**
Use-cases d'authentification. Attribution auto des rôles via
`config.domains` (paramétrable).

**Checklist**
- [ ] `Application/Services/RegisterUserService.php` (lit `config.domains` pour rôles auto)
- [ ] `Application/Services/LoginService.php` (verify + recordLogin)
- [ ] `Application/DTOs/RegisterRequest.php`
- [ ] Tests : domaine `@etu.univ-amu.fr` → rôle student auto

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §4, §2 (encadré domaines)

---

### [A-04] App services : Password reset (request + reset)

**Labels** : `must` `app` `spec-01`
**Points** : 3

**Description**
Flow oubli de mot de passe : envoi de token, réception, changement.

**Checklist**
- [ ] `RequestPasswordResetService` : génère token aléatoire (32 bytes hex), expire 1h, envoie via `MailerInterface`
- [ ] `ResetPasswordService` : vérifie token, change mdp, invalide tous les tokens du user
- [ ] `PasswordResetToken` Value Object (token, userId, expiresAt)
- [ ] Tests : token expiré → exception, token utilisé 2x → exception

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §4

---

### [A-05] App services : profil, mot de passe, préférences, RGPD, suppression

**Labels** : `must` `app` `spec-01`
**Points** : 3

**Description**
Tous les use-cases de gestion de compte côté utilisateur.

**Checklist**
- [ ] `UpdateProfileService` (first_name, last_name)
- [ ] `ChangePasswordService` (vérif ancien + hash nouveau)
- [ ] `UpdatePreferencesService` (`conversation_archive_days` 30..3650)
- [ ] `GrantGdprConsentService` / `RevokeGdprConsentService`
- [ ] `DeleteAccountService` (soft-delete, anonymise email)
- [ ] `GetAccountOverviewService` → `AccountOverviewView` (agrégats)

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §4

---

### [A-06] Infrastructure : PdoUserRepository + PdoPasswordResetRepository

**Labels** : `must` `infra` `spec-01`
**Points** : 5

**Description**
Implémentations PDO des interfaces. Hydrate User depuis 5 tables
(`"user"`, `student`, `teacher`, `researcher`, `administrator`).

**Checklist**
- [ ] `Infrastructure/Persistence/PdoUserRepository.php` (toutes les méthodes de l'interface)
- [ ] `Infrastructure/Persistence/PdoPasswordResetRepository.php`
- [ ] `Infrastructure/Persistence/UserRowHydrator.php` (helper d'hydratation)
- [ ] Tests d'intégration : round-trip `save` puis `findById`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §5

---

### [A-07] Infrastructure : MailerInterface + PhpMailerSender + LogMailerSender

**Labels** : `must` `infra` `spec-01`
**Points** : 3

**Description**
Port d'envoi d'emails + 2 adapters (prod SMTP + dev log).

**Checklist**
- [ ] `Application/Ports/MailerInterface.php` (`send(to, subject, htmlBody, textBody)`)
- [ ] `Infrastructure/Mail/PhpMailerSender.php` (vraie connexion SMTP, lit `config.mail`)
- [ ] `Infrastructure/Mail/LogMailerSender.php` (écrit dans `var/log/mails-YYYY-MM-DD.log`)
- [ ] Bootstrap : choix du mailer selon `config.app.debug`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §5

---

### [A-08] Http : AuthController (login / register / logout)

**Labels** : `must` `http` `spec-01`
**Points** : 3

**Description**
3 endpoints d'authentification.

**Checklist**
- [ ] `Http/Controllers/AuthController.php`
- [ ] `GET /login` + `POST /login` (CSRF, flash error si bad creds)
- [ ] `GET /register` + `POST /register`
- [ ] `GET /logout` (détruit session, redirect /)
- [ ] Validation via `Validator` + `Forms/LoginForm`, `Forms/RegisterForm`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §6

---

### [A-09] Http : PasswordResetController

**Labels** : `must` `http` `spec-01`
**Points** : 2

**Description**
4 endpoints du flow reset.

**Checklist**
- [ ] `GET /password/forgot` + `POST /password/forgot` (envoie le mail)
- [ ] `GET /password/reset/{token}` + `POST /password/reset` (change le mdp)
- [ ] Anti-énumération : toujours flasher "Si un compte existe…" (même si l'email n'existe pas)

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §6

---

### [A-10] Http : AccountController + GdprController

**Labels** : `must` `http` `spec-01`
**Points** : 3

**Description**
Page Mon compte + endpoints RGPD basiques (consentement bloquant).

**Checklist**
- [ ] `GET /account` → `AccountController::index` (vue + ViewModel)
- [ ] `POST /account/profile` (UpdateProfileService)
- [ ] `POST /account/password` (ChangePasswordService)
- [ ] `POST /account/preferences` (UpdatePreferencesService - durée d'archivage)
- [ ] `POST /account/revoke-consent` (Revoke + déconnexion)
- [ ] `POST /account/delete` (DeleteAccountService)
- [ ] `GET /gdpr/consent` + `POST /gdpr/consent`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §6 (le détail RGPD avancé est en spec 06)

---

### [A-11] Views auth/* (login, register, forgot, reset, gdpr_consent)

**Labels** : `must` `view` `spec-01`
**Points** : 3

**Description**
5 vues d'authentification.

**Checklist**
- [ ] `Views/pages/auth/login.php`
- [ ] `Views/pages/auth/register.php`
- [ ] `Views/pages/auth/forgot.php`
- [ ] `Views/pages/auth/reset.php`
- [ ] `Views/pages/auth/gdpr_consent.php`
- [ ] Toutes incluent `csrf_field()`

**Référence** : POC réutilisable : `git show poc:app/views/pages/auth/`

---

### [A-12] View account/index.php (5 sections)

**Labels** : `must` `view` `spec-01`
**Points** : 3

**Description**
Page Mon compte avec sidebar interne et 5 sections (profil, apparence,
données, consentement recherche, zone risquée).

**Checklist**
- [ ] Sidebar interne + highlight scroll
- [ ] Section Profil : identifiant + n° étudiant + rôle + email + champs éditables
- [ ] Section Apparence : segmented toggles thème/densité/langue (localStorage)
- [ ] Section Données : conversations, interactions, bytes, durée d'archivage modifiable
- [ ] Section Consentement recherche : état + bouton retrait (renvoie à spec 06 pour opposition)
- [ ] Section Zone risquée : changer mdp + supprimer compte (collapsibles)

**Référence** : POC : `git show poc:app/views/pages/account/index.php`

---

### [A-13] DB : migration user-preferences + password_reset

**Labels** : `must` `db` `spec-01`
**Points** : 1

**Description**
Migration SQL.

**Checklist**
- [ ] `database/migrations/AAAA-MM-DD-user-preferences.sql`
- [ ] `ALTER TABLE "user" ADD COLUMN conversation_archive_days INT NOT NULL DEFAULT 180`
- [ ] `CREATE TABLE password_reset (token, user_id, expires_at, created_at)`
- [ ] Appliquée sur la DB de dev + ajoutée à `schema.sql`

**Référence** : [`specs/01-auth-account.md`](../specs/01-auth-account.md) §7

---

## 📋 Liste 3 — `02-sessions`

### [S-01] Domain : Session + VOs (Type, Status, AccessCode)

**Labels** : `must` `domain` `spec-02`
**Points** : 3

**Description**
Entité Session avec son cycle de statuts et ses invariants métier.

**Checklist**
- [ ] `Domain/Entities/Session.php` (rename, reschedule, start, end, cancel, canBeModified, isActive, computedStatus)
- [ ] `Domain/ValueObjects/SessionType.php` (enum TP/EXAM/SANDBOX)
- [ ] `Domain/ValueObjects/SessionStatus.php` (enum DRAFT/SCHEDULED/ACTIVE/ENDED/CANCELLED)
- [ ] `Domain/ValueObjects/AccessCode.php` (6 chars [A-Z0-9], auto-validation)
- [ ] Tests : `start()` lance si CANCELLED, `cancel()` lance si ENDED

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §3

---

### [S-02] Domain : SessionRepositoryInterface + exceptions

**Labels** : `must` `domain` `spec-02`
**Points** : 1

**Description**
Contrat de persistence + exceptions métier.

**Checklist**
- [ ] `Domain/Repositories/SessionRepositoryInterface.php` (findById, findByAccessCode, findAllByTeacher, save, authorizedModelIdsOf, setAuthorizedModels, generateUniqueAccessCode)
- [ ] 5 exceptions : `SessionNotFoundException`, `SessionAlreadyStartedException`, `SessionAlreadyEndedException`, `SessionCancelledException`, `SessionNotEditableException`

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §3

---

### [S-03] App : CreateSession + UpdateSession + JoinSession

**Labels** : `must` `app` `spec-02`
**Points** : 3

**Description**
Use-cases CRUD principaux.

**Checklist**
- [ ] `CreateSessionService` (calcule ends_at depuis duration_minutes)
- [ ] `UpdateSessionService` (respecte canBeModified)
- [ ] `JoinSessionService` (étudiant rejoint via AccessCode)
- [ ] `CreateSessionRequest` DTO (avec `postPrompt` ajouté)

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §4

---

### [S-04] App : Start / End / Cancel + listing

**Labels** : `must` `app` `spec-02`
**Points** : 2

**Description**
Use-cases de lifecycle.

**Checklist**
- [ ] `StartSessionService` (fixe starts_at à NOW si vide)
- [ ] `EndSessionService` (fixe ends_at à NOW si vide)
- [ ] `CancelSessionService`
- [ ] `ListMySessionsService` → `SessionListView[]`
- [ ] `GetSessionDashboardService` → `SessionDashboardView`

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §4

---

### [S-05] Infra : PdoSessionRepository + generateUniqueAccessCode

**Labels** : `must` `infra` `spec-02`
**Points** : 3

**Description**
Implémentation PDO + génération de code unique.

**Checklist**
- [ ] `PdoSessionRepository` (hydratation Session ↔ row + relation `authorizes`)
- [ ] `generateUniqueAccessCode()` : `random_bytes(3)` → hex upper, boucle si collision
- [ ] Tests d'intégration : 1000 codes générés, aucun doublon
- [ ] Tests : `save()` puis `findById()` round-trip exact

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §5

---

### [S-06] Http : SessionController (8 endpoints)

**Labels** : `must` `http` `spec-02`
**Points** : 5

**Description**
Tous les endpoints HTTP des sessions.

**Checklist**
- [ ] `GET /sessions` (liste)
- [ ] `GET /sessions/create` (formulaire avec previewCode)
- [ ] `POST /sessions/store`
- [ ] `GET /sessions/{id}/edit`
- [ ] `POST /sessions/{id}/update`
- [ ] `POST /sessions/{id}/start`, `/end`, `/cancel`
- [ ] `GET /sessions/{id}` (dashboard)
- [ ] `POST /sessions/join` (étudiant)

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §6

---

### [S-07] View session/index : table desktop + cards mobile

**Labels** : `must` `view` `spec-02`
**Points** : 3

**Description**
Liste des sessions avec basculement responsive.

**Checklist**
- [ ] Table desktop avec `<th>` Actions invisible (`sr-only`)
- [ ] Icônes contextuelles selon `availableActions` : œil / crayon / play / square / x-circle
- [ ] Cards mobile (< 768px) avec menu 3 points `<details>`
- [ ] CSS : `.icon-btn`, `.card-menu`, breakpoint 768px

**Référence** : POC : `git show poc:app/views/pages/session/index.php`

---

### [S-08] View session/create.php (preview live + checklist)

**Labels** : `must` `view` `spec-02`
**Points** : 5

**Description**
Layout 2 colonnes complexe avec JS de preview en temps réel.

**Checklist**
- [ ] Layout 2 colonnes : formulaire + panneau code/preview
- [ ] Type cards visuelles (Cours/Examen)
- [ ] Champs : libellé, planification (démarre + durée min + auto-clôture), modèles autorisés, pré-prompt, post-prompt, instructions, taille max
- [ ] Code d'accès géant + boutons Copier / Plein écran / QR (placeholder)
- [ ] Aperçu étudiant qui se met à jour en live
- [ ] Checklist "Avant de créer" (Ollama, modèles, cohérence)

**Référence** : POC : `git show poc:app/views/pages/session/create.php`

---

### [S-09] View session/edit.php + dashboard.php

**Labels** : `must` `view` `spec-02`
**Points** : 3

**Description**
Vue d'édition (reprend create) + vue dashboard avec actions
contextuelles.

**Checklist**
- [ ] `edit.php` : code en lecture seule + bouton Copier
- [ ] `dashboard.php` : header avec badges + barre d'actions selon statut (Modifier, Démarrer, Superviser, Terminer, Annuler)
- [ ] Stats : étudiants connectés, modèles autorisés, prompts envoyés, durée prévue

**Référence** : POC : `git show poc:app/views/pages/session/edit.php`, `dashboard.php`

---

### [S-10] DB : migration session-status + post_prompt_override

**Labels** : `must` `db` `spec-02`
**Points** : 1

**Description**
Migration colonnes session.

**Checklist**
- [ ] `CREATE TYPE session_status AS ENUM (...)`
- [ ] `ALTER TABLE session ADD COLUMN status`
- [ ] `ALTER TABLE session ADD COLUMN post_prompt_override TEXT`
- [ ] `database/migrations/AAAA-MM-DD-session-status.sql`

**Référence** : [`specs/02-sessions.md`](../specs/02-sessions.md) §7

---

## 📋 Liste 4 — `03-chat-llm`

### [C-01] Domain : Conversation + Interaction + LlmModel + VOs

**Labels** : `must` `domain` `spec-03`
**Points** : 5

**Description**
3 entités centrales + leurs VOs.

**Checklist**
- [ ] `Domain/Entities/Conversation.php` (rename, archive, submit, belongsTo)
- [ ] `Domain/Entities/Interaction.php` (rateByUser, flagByTeacher, clearFlag)
- [ ] `Domain/Entities/LlmModel.php` (activate, deactivate, tag())
- [ ] `Domain/ValueObjects/ConversationType.php` (enum FREE/COURSE/EXAM)
- [ ] `Domain/ValueObjects/TeacherFlag.php` (flagged, reason, comment)
- [ ] `Application/Ports/LlmResponse.php` + `LlmModelInfo.php`

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §3

---

### [C-02] Domain : Repositories interfaces + LlmProviderInterface (port)

**Labels** : `must` `domain` `app` `spec-03`
**Points** : 2

**Description**
3 interfaces de repository + le port LLM.

**Checklist**
- [ ] `ConversationRepositoryInterface`
- [ ] `InteractionRepositoryInterface`
- [ ] `LlmModelRepositoryInterface`
- [ ] `Application/Ports/LlmProviderInterface.php` (`chat`, `chatStream`, `listModels`, `isAvailable`)

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §3

---

### [C-03] App : Conversation lifecycle + Send/Stream prompt

**Labels** : `must` `app` `spec-03`
**Points** : 5

**Description**
Use-cases du chat. **Préserver le tag Ollama exact** (pas de
`strtolower`, bug repéré dans le POC).

**Checklist**
- [ ] `StartConversationService`
- [ ] `SendStudentPromptService` (synchrone)
- [ ] `StreamStudentPromptService` (SSE, callable $onChunk)
- [ ] `RateInteractionService` (-1/0/1)
- [ ] `ArchiveConversationService`
- [ ] Règle métier : si mode EXAM, vérifier qu'aucune autre conv ouverte du user pour cette session

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §4

---

### [C-04] App : SyncOllamaModelsService + CheckOllamaStatusService

**Labels** : `must` `app` `spec-03`
**Points** : 2

**Description**
Synchro modèles + healthcheck. Avec cache 5 min.

**Checklist**
- [ ] `SyncOllamaModelsService` (added/reactivated/disabled) — cache fichier 5 min
- [ ] `CheckOllamaStatusService` → `OllamaStatusView` (online + count)
- [ ] Bouton manuel `POST /admin/models/sync` invalide le cache

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §4

---

### [C-05] Infra : Pdo*Repositories (Conversation, Interaction, LlmModel)

**Labels** : `must` `infra` `spec-03`
**Points** : 5

**Description**
3 implémentations PDO. Attention à la PK `prompt_id` (pas
`interaction_id`).

**Checklist**
- [ ] `PdoConversationRepository`
- [ ] `PdoInteractionRepository` (PK = `prompt_id`)
- [ ] `PdoLlmModelRepository`
- [ ] Tests d'intégration round-trip pour chacun

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §5

---

### [C-06] Infra : OllamaLlmProvider + FakeLlmProvider

**Labels** : `must` `infra` `tests` `spec-03`
**Points** : 5

**Description**
Adapter Ollama via cURL + fake pour tests.

**Checklist**
- [ ] `Infrastructure/Llm/OllamaLlmProvider.php`
  - `chat()` : `POST /api/chat` synchrone
  - `chatStream()` : `POST /api/chat?stream=true`, parsing JSON ligne par ligne via `CURLOPT_WRITEFUNCTION`
  - `listModels()` : `GET /api/tags`
  - `isAvailable()` : `GET /api/tags` avec timeout court
- [ ] `Infrastructure/Llm/FakeLlmProvider.php` (réponses fixes pour tests)
- [ ] **Pas de `strtolower($model)`** — tag préservé

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §5

---

### [C-07] Http : ChatController (8 endpoints) + SSE streaming

**Labels** : `must` `http` `spec-03`
**Points** : 5

**Description**
Endpoints chat avec streaming Server-Sent Events.

**Checklist**
- [ ] `GET /chat` + `GET /chat/{id}` (vues)
- [ ] `POST /chat/create` (nouvelle conversation)
- [ ] `POST /chat/send` (synchrone, retourne JSON)
- [ ] `POST /chat/stream` (SSE, `Content-Type: text/event-stream`, `ob_flush()` + `flush()`)
- [ ] `POST /chat/{id}/archive`
- [ ] `GET /chat/ollama/status` (AJAX pour pastille navbar)
- [ ] Header `X-Accel-Buffering: no` pour Apache

**Référence** : [`specs/03-chat-llm.md`](../specs/03-chat-llm.md) §6

---

### [C-08] View chat/index.php + assets JS client SSE

**Labels** : `must` `view` `spec-03`
**Points** : 5

**Description**
Vue de chat avec sidebar conversations + zone messages + select modèle.
JS SSE pour streaming.

**Checklist**
- [ ] Sidebar conversations (`findByUser`)
- [ ] Zone de chat avec markdown (marked.js) + highlight.js + bouton copier code
- [ ] Select modèle (synchronisé avec DB, pas hardcodé)
- [ ] JS : `EventSource` sur `/chat/stream`, appendChunk au DOM
- [ ] Auto-resize du textarea
- [ ] Indicateur de statut Ollama (pastille verte/rouge polling 30s)

**Référence** : POC : `git show poc:app/views/pages/chat/index.php`, `public/assets/js/app.js`

---

## 📋 Liste 5 — `04-supervise`

### [SU-01] DB : migration teacher-supervision

**Labels** : `must` `db` `spec-04`
**Points** : 1

**Description**
Colonnes pour signalement + archivage.

**Checklist**
- [ ] `ALTER TABLE interaction ADD COLUMN teacher_flag, teacher_flag_reason, teacher_comment`
- [ ] Index partiel sur `teacher_flag <> 0`
- [ ] `ALTER TABLE conversation ADD COLUMN submitted_at`
- [ ] `database/migrations/AAAA-MM-DD-teacher-supervision.sql`

**Référence** : [`specs/04-supervise.md`](../specs/04-supervise.md) §7

---

### [SU-02] App : Supervision view + Flag + Archive

**Labels** : `should` `app` `spec-04`
**Points** : 5

**Description**
Use-cases supervision enseignant.

**Checklist**
- [ ] `GetSupervisionViewService(sessionId, ?selectedConvId, tab)` → `SupervisionView`
- [ ] `FlagPromptService(interactionId, reason, comment)`
- [ ] `ClearPromptFlagService(interactionId)`
- [ ] `ArchiveSessionService(sessionId)` (toutes conv → `submitted_at = NOW`)
- [ ] DTOs : `SupervisionStudentRow`, `SupervisionView`, `ConversationDetailView`

**Référence** : [`specs/04-supervise.md`](../specs/04-supervise.md) §4

---

### [SU-03] Infra : PdoSessionSupervisionRepository (requête agrégée)

**Labels** : `should` `infra` `spec-04`
**Points** : 5

**Description**
Une seule requête SQL agrégée pour lister les étudiants + dernier
prompt + nb signalés.

**Checklist**
- [ ] `supervisionRowsForSession(sessionId)` avec sous-requêtes corrélées (dernier prompt, dernier sent_at, dernier model, prompt_count, flag_count)
- [ ] Jointure `"user"` + `student`
- [ ] Tests d'intégration : 3 étudiants dont 1 signalé → `flag_count` correct

**Référence** : [`specs/04-supervise.md`](../specs/04-supervise.md) §5

---

### [SU-04] Http : ExamController (supervise + flag + archive + poll)

**Labels** : `should` `http` `spec-04`
**Points** : 3

**Description**
Endpoints supervision.

**Checklist**
- [ ] `GET /exam/{id}/supervise` (vue split + filtres + détail conv)
- [ ] `POST /exam/flag` + `POST /exam/unflag`
- [ ] `POST /exam/{id}/archive`
- [ ] `GET /exam/{id}/poll` (AJAX live update)

**Référence** : [`specs/04-supervise.md`](../specs/04-supervise.md) §6

---

### [SU-05] View exam/supervise.php (split view + tabs + form signalement)

**Labels** : `should` `view` `spec-04`
**Points** : 5

**Description**
Vue complexe à 2 colonnes.

**Checklist**
- [ ] Header : nom session + N étudiants + code + temps écoulé + actions archiver/note classe
- [ ] Colonne gauche : recherche + 4 onglets + tableau étudiants (avatar, nom, dernier prompt, modèle, activité)
- [ ] Colonne droite : header conv (avatar + flag badge si signalée) + conversation alternée élève/LLM + commentaires de marge en rouge
- [ ] Form signalement : select raison + input commentaire + bouton

**Référence** : POC : `git show poc:app/views/pages/exam/supervise.php`

---

## 📋 Liste 6 — `05-admin-research`

### [AD-01] App : Admin services (dashboard + users + models toggle + config)

**Labels** : `should` `app` `spec-05`
**Points** : 3

**Description**
Use-cases admin.

**Checklist**
- [ ] `GetAdminDashboardService` → COUNT agrégats
- [ ] `ListUsersService(search, page, perPage)` → `UserListView`
- [ ] `AttachRoleToUserService` / `DetachRoleFromUserService`
- [ ] `ToggleLlmModelService(modelId, active)`
- [ ] `GetConfigOverviewService` → `ConfigOverviewView`

**Référence** : [`specs/05-admin-research.md`](../specs/05-admin-research.md) §A.2

---

### [AD-02] Http : AdminController (4 pages)

**Labels** : `should` `http` `spec-05`
**Points** : 3

**Description**
Endpoints admin.

**Checklist**
- [ ] `GET /admin` (dashboard)
- [ ] `GET /admin/users` + `POST /admin/users/role`
- [ ] `GET /admin/models` + `POST /admin/models/toggle` + `POST /admin/models/sync`
- [ ] `GET /admin/config`
- [ ] **Pas d'endpoint d'ajout manuel** (retiré en POC)

**Référence** : [`specs/05-admin-research.md`](../specs/05-admin-research.md) §A.3

---

### [AD-03] Views admin/* (4 pages)

**Labels** : `should` `view` `spec-05`
**Points** : 3

**Description**
4 vues admin réutilisables depuis POC.

**Checklist**
- [ ] `admin/index.php` (dashboard stats)
- [ ] `admin/users.php` (wrapper `.roles-cell-wrap` + `.cell-top` corrigés)
- [ ] `admin/models.php` (sans formulaire d'ajout, juste sync + toggle)
- [ ] `admin/config.php`

**Référence** : POC : `git show poc:app/views/pages/admin/`

---

### [AD-04] App : Research corpus build + export

**Labels** : `should` `app` `rgpd` `spec-05`
**Points** : 5

**Description**
Dashboard chercheur — agrégations + filtre RGPD opposition.

**Checklist**
- [ ] `ResearchFilters` DTO (période, cours, modèle, rôle, longueur min, anonymisé)
- [ ] `BuildResearchCorpusService(filters)` → `ResearchCorpusView`
- [ ] `ExportResearchCorpusService(filters)` → JSON string
- [ ] **Filtre obligatoire** : `WHERE u.research_opposed = FALSE AND u.gdpr_consent = TRUE`
- [ ] Tests : un user opposé n'apparaît pas dans `byCourse`

**Référence** : [`specs/05-admin-research.md`](../specs/05-admin-research.md) §B.2, [`specs/06-rgpd.md`](../specs/06-rgpd.md) §8

---

### [AD-05] Infra : PdoResearchRepository (6 agrégations)

**Labels** : `should` `infra` `spec-05`
**Points** : 5

**Description**
Toutes les requêtes d'agrégation centralisées.

**Checklist**
- [ ] `byCourse` (top 12 horizontal bar)
- [ ] `byWeek` (date_trunc week)
- [ ] `byHour` (EXTRACT HOUR)
- [ ] `byLength` (CASE WHEN buckets 0+, 20+, 50+, 100+, 200+, 500+, 1000+)
- [ ] stats globales (cours, étudiants, prompts, bytes)
- [ ] extraction de mots-clés (regex + stopwords FR/EN)

**Référence** : [`specs/05-admin-research.md`](../specs/05-admin-research.md) §B.3

---

### [AD-06] Http + View : ResearchController + dashboard/export.php

**Labels** : `should` `http` `view` `spec-05`
**Points** : 5

**Description**
Page dashboard chercheur avec charts SVG inline.

**Checklist**
- [ ] `GET /export` + `GET /export/json`
- [ ] Header + stats + actions (enregistrer vue localStorage, notebook, exporter)
- [ ] 6 filtres en chips
- [ ] 4 charts SVG/HTML générés en PHP
- [ ] Nuage de mots-clés
- [ ] **Toggle "anonymisé"** : cosmétique uniquement, masque les noms à l'écran (l'export reste avec les noms — cf. spec 06)

**Référence** : POC : `git show poc:app/views/pages/dashboard/export.php`

---

## 📋 Liste 7 — `06-rgpd`

### [R-01] DB : migration rgpd-compliance

**Labels** : `must` `db` `rgpd` `spec-06`
**Points** : 1

**Description**
Colonnes opposition recherche + table journal.

**Checklist**
- [ ] `ALTER TABLE "user" ADD COLUMN research_opposed BOOLEAN DEFAULT FALSE`
- [ ] `ALTER TABLE "user" ADD COLUMN gdpr_consent_revoked_at TIMESTAMP`
- [ ] `CREATE TABLE data_access_log (id, actor_user_id, action, target_user_id, context JSONB, ip_address, at)`
- [ ] Index `actor_user_id + at DESC` et `action + at DESC`

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §7

---

### [R-02] Domain : extension GdprConsent + DataAccessLog entity

**Labels** : `must` `domain` `rgpd` `spec-06`
**Points** : 2

**Description**
VO consentement enrichi + entité de log.

**Checklist**
- [ ] `Domain/ValueObjects/GdprConsent.php` enrichi : `researchOpposed`, `revokedAt`, méthodes `opposeResearch`, `acceptResearch`
- [ ] `Domain/Entities/DataAccessLog.php` (id, actorUserId, action, targetUserId, context, ipAddress, at)
- [ ] `Domain/Repositories/DataAccessLogRepositoryInterface.php` (append, findForUser, findRecent)

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §3

---

### [R-03] App : Privacy notice + Export my data + Opposition recherche

**Labels** : `must` `app` `rgpd` `spec-06`
**Points** : 3

**Description**
Use-cases des 4 droits RGPD.

**Checklist**
- [ ] `ShowPrivacyNoticeService` → `PrivacyNoticeView`
- [ ] `ExportMyDataService(userId)` → JSON (profil + conversations + interactions + sessions)
- [ ] `OpposeResearchUseService(userId)` + log
- [ ] `AcceptResearchUseService(userId)` + log
- [ ] `LogDataAccessService(actorId, action, targetId, ctx)` — appelé par les autres services

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §4

---

### [R-04] Infra : PdoDataAccessLogRepository

**Labels** : `must` `infra` `rgpd` `spec-06`
**Points** : 2

**Description**
Persistance append-only du journal.

**Checklist**
- [ ] `PdoDataAccessLogRepository::append(DataAccessLog)`
- [ ] `findForUser(userId, limit)` (ordre at DESC)
- [ ] `findRecent(limit)`
- [ ] **Pas de méthode `delete`** (append-only)

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §5

---

### [R-05] Intégration : appels LogDataAccess dans les services sensibles

**Labels** : `must` `app` `rgpd` `spec-06`
**Points** : 2

**Description**
Brancher la journalisation dans les 5 actions sensibles.

**Checklist**
- [ ] `ExportResearchCorpusService` → log `export_corpus`
- [ ] `GetSupervisionViewService` ou `ConversationDetailView` → log `view_user_conversation` (1 log par conv consultée)
- [ ] `AttachRoleToUserService` + `DetachRoleFromUserService` → log `role_change`
- [ ] `DeleteAccountService` → log `account_deleted`
- [ ] Tests : vérifier l'apparition d'une ligne en DB pour chacun

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §4

---

### [R-06] Http : PrivacyController + extensions Account/Admin

**Labels** : `must` `http` `rgpd` `spec-06`
**Points** : 3

**Description**
Endpoints RGPD côté HTTP.

**Checklist**
- [ ] `GET /privacy` (public, sans authent)
- [ ] `GET /account/data-export` (droit d'accès, log déclenché)
- [ ] `POST /account/oppose-research` + `POST /account/accept-research`
- [ ] `GET /admin/data-access-log` (rôle admin)

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §6

---

### [R-07] Views : legal/privacy.php + maj account/index.php

**Labels** : `must` `view` `rgpd` `spec-06`
**Points** : 2

**Description**
Mention d'information CNIL + toggle opposition dans /account.

**Checklist**
- [ ] `Views/legal/privacy.php` (modèle CNIL avec placeholders → config `rgpd.controller_name`, `rgpd.controller_contact`, etc.)
- [ ] Lien depuis le footer global vers `/privacy`
- [ ] `account/index.php` section "Consentement recherche" : ajout toggle **"Je m'oppose à figurer dans les corpus de recherche"**
- [ ] Bouton "Télécharger mes données (droit d'accès)" → `/account/data-export`

**Référence** : [`specs/06-rgpd.md`](../specs/06-rgpd.md) §6, rapport §5.2 (modèle CNIL)

---

## 📋 Liste 8 — Should/Could-have additionnels (gap-analysis)

### [G-01] Import LLM personnalisés (Enseignant Spécialisé)

**Labels** : `should` `app` `infra` `http`
**Points** : 5

**Description**
Permettre à un teacher_specialised d'importer un modèle via `ollama
pull`. Sans ça, le rôle `teacher_specialised` n'a aucune utilité
distinctive.

**Checklist**
- [ ] `Application/Services/PullLlmModelService(string $tag, int $userId)`
- [ ] Endpoint `POST /admin/models/import` (rôle teacher_specialised OU admin)
- [ ] Adapter `OllamaLlmProvider::pull(string $tag, callable $onProgress)` → `POST /api/pull` streamé
- [ ] Polling SSE pour suivre l'avancement
- [ ] À la fin : appel auto `SyncOllamaModelsService`
- [ ] View : formulaire dans `/admin/models` + zone de progression

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.1

---

### [G-02] Retrait d'un étudiant d'un examen en cours

**Labels** : `should` `app` `http` `spec-04`
**Points** : 3

**Description**
Permettre à l'enseignant de couper l'accès à un étudiant spécifique
sans terminer toute la session.

**Checklist**
- [ ] `RemoveStudentFromExamService(conversationId, teacherId)`
- [ ] Effet : `conversation.is_archived = true`, `submitted_at = NOW`
- [ ] Endpoint `POST /exam/{sessionId}/remove-student/{conversationId}`
- [ ] JS étudiant : polling toutes les 5s sur `/exam/conversation/status`, redirige si archivée
- [ ] Bouton dans la vue supervise (icône `x` à côté de chaque ligne)

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.2

---

### [G-03] Verrouillage UI mode examen (formalisation)

**Labels** : `should` `view` `spec-04`
**Points** : 2

**Description**
Layout examen séparé, sans navbar, avec timer + détection sortie
d'onglet.

**Checklist**
- [ ] `Views/layout/exam.php` (logo + nom user + timer, pas de navbar)
- [ ] JS : `window.addEventListener('beforeunload', confirm)` actif en mode examen
- [ ] Bouton "passer en plein écran" suggéré au lancement (`requestFullscreen()`)
- [ ] Pas de bouton logout accessible
- [ ] Log d'événement si l'étudiant quitte (`document.visibilitychange`)

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.3

---

### [G-04] Vérification IP en examen

**Labels** : `could` `app` `db` `spec-04`
**Points** : 3

**Description**
Détecter et alerter sur changement d'IP pendant un examen.

**Checklist**
- [ ] Migration : `ALTER TABLE conversation ADD COLUMN join_ip VARCHAR(45)`
- [ ] À la jonction (`JoinSessionService`) : enregistrer l'IP source
- [ ] À chaque prompt en mode EXAM : si IP différente, marquer un `teacher_flag` automatique avec raison `ip-change`
- [ ] Badge "IP changée" dans la vue supervise

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.4

---

### [G-05] Multi-modèle dans une conversation (documentation)

**Labels** : `should` `view` `spec-03`
**Points** : 1

**Description**
Le code permet déjà plusieurs modèles dans une même conv, mais ce n'est
ni documenté ni mis en valeur UI.

**Checklist**
- [ ] Ajouter section dédiée dans `specs/03-chat-llm.md` §3
- [ ] Le ViewModel `ConversationDetailView` liste `modelsUsedInConv: list<string>`
- [ ] L'UI affiche un mini-récap "3 modèles utilisés sur 12 tours"

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.5

---

### [G-06] Texte CNIL définitif

**Labels** : `must` `rgpd`
**Points** : 1 (action externe)

**Description**
Récupérer auprès du DPO universitaire le texte définitif de la mention
d'information. Action **externe** (pas du code).

**Checklist**
- [ ] Contacter le DPO AMU : `dpo@univ-amu.fr`
- [ ] Récupérer : identité responsable, base légale, durée conservation exacte
- [ ] Mettre à jour `config.rgpd.*`
- [ ] Mettre à jour `Views/legal/privacy.php` avec le texte final

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.6, rapport §5.2

---

### [G-07] Purge automatique des logs RGPD

**Labels** : `could` `infra` `rgpd` `spec-06`
**Points** : 2

**Description**
Au-delà de 3 ans, supprimer les entrées `data_access_log` (durée légale
écoulée).

**Checklist**
- [ ] Service `PurgeOldAccessLogsService(daysToKeep = 1095)`
- [ ] Script CLI `bin/purge-logs.php` lançable via cron
- [ ] Crontab mensuel dans `docker/cron.dockerfile` (ou doc setup)
- [ ] Endpoint admin `POST /admin/logs/purge?before=date` pour purge ponctuelle

**Référence** : [`documentation/gap-analysis.md`](./gap-analysis.md) §N3.7

---

## 📋 Liste 9 — Backlog "client-blocking"

Cartes **en attente de réponse client** (gap-analysis §Niveau 2). À
trier après réunion.

### [Q-01] Décision : code examen partagé ou un par étudiant ?

**Labels** : `blocked` `should`
**Points** : ?

**Description**
Bloque la conception finale du flow d'inscription en examen. Voir
gap-analysis §Q1.

**À faire après décision** :
- Si "1 code par classe" : conserver l'implémentation actuelle (POC).
- Si "1 code par étudiant" : créer table `session_invite`, refactor
  `JoinSessionService`, refondre le flow de partage des codes.

---

### [Q-02] Décision : multi-rôles sur un compte ou séparation ?

**Labels** : `blocked` `should`
**Points** : ?

**Description**
Voir gap-analysis §Q2.

**À faire après décision** :
- Si "multi-rôles OK" : pas de code, l'archi actuelle gère.
- Si "séparation" : ajouter règle dans `AttachRoleToUserService` qui
  empêche le cumul des rôles "privilégiés".

---

### [Q-03] Décision : upload doc — simple ou RAG ?

**Labels** : `blocked` `could`
**Points** : ?

**Description**
Voir gap-analysis §Q3.

**À faire après décision** :
- Si "simple" : `session.attached_document_text` + concaténation
  pré-prompt (carte ~3pts).
- Si "RAG" : épopée à part entière (vector store, embeddings, retrieval
  — au moins 13pts à découper).

---

### [Q-04] Décision : toggle "anonymisé" — cosmétique ou export ?

**Labels** : `blocked` `should`
**Points** : 1

**Description**
Voir gap-analysis §Q4. Si la décision est "cosmétique uniquement",
juste documenter clairement et finir l'implémentation actuelle.

---

### [Q-05] Décision : valeurs défaut durée d'archivage

**Labels** : `blocked` `should`
**Points** : 1

**Description**
Voir gap-analysis §Q5.

**À faire après décision** :
- Valeur défaut (suggestion 180)
- Borne max (suggestion 1095)
- Action en fin de durée (delete vs is_archived)
- Adapter `UpdatePreferencesService` + spec 01

---

## 🎯 Récapitulatif

| Liste | Cartes | Total pts |
|---|---|---|
| 00-foundations  | 8  | 18 |
| 01-auth-account | 13 | 36 |
| 02-sessions     | 10 | 30 |
| 03-chat-llm     | 8  | 34 |
| 04-supervise    | 5  | 19 |
| 05-admin-research | 6 | 24 |
| 06-rgpd         | 7  | 15 |
| Gap N3          | 7  | 17 |
| Bloquées (Q)    | 5  | ? |
| **Total**       | **69 cartes** | **~193 pts** |

À ~5 pts/jour-personne, ça représente **~40 jours-personne** soit
**~2 sprints de 2 semaines pour 4 personnes**. Cohérent avec un projet
SAE.

---

## 📥 Import dans Trello

1. Créer un board **I-AMU** avec 1 liste = 1 section de ce fichier.
2. Créer les 12 labels avec les couleurs suggérées.
3. Pour chaque carte :
   - Titre : copier-coller la ligne `### [...]`
   - Description : copier le bloc `**Description**` + `**Checklist**` + `**Référence**`
   - Trello convertit `- [ ]` en checklists si on les copie dans une **checklist** (pas dans la description).
4. Activer le power-up *Story Points* (ou utiliser le custom field) pour les estimations.

Optionnel : un script `bin/seed-trello.php` qui utilise l'API Trello
pour créer board + listes + cartes automatiquement à partir de ce
fichier. Pas prioritaire.
