# Spec 02 — Sessions

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations, 01-auth-account
- **État (2026-06-09)** : **livré** côté enseignant (CRUD + cycle de vie +
  code d'accès) **et** côté étudiant : `join` enrôle + crée la conversation
  liée (cf. [SPEC-session-chat-join](./SPEC-session-chat-join.md)), suivi
  live (cf. [SPEC-session-monitor](./SPEC-session-monitor.md)), co-supervision
  (cf. [SPEC-session-supervisors](./SPEC-session-supervisors.md)), export
  recherche (cf. [SPEC-session-export](./SPEC-session-export.md)), documents
  de session (cf. [SPEC-documents-rag](./SPEC-documents-rag.md)). Reste à
  faire : compteur temps réel / preflight Ollama réel.

> ⚠️ **Recadrage.** Ce cahier des charges décrit la cible **initiale** en
> archi hexagonale ; plusieurs valeurs ci-dessous ont évolué et **font foi
> dans le code** :
> - **4 types** `EXAM` / `TUTORIAL` / `LAB` / `FREE_STUDY` (et non
>   `TP`/`EXAM`/`SANDBOX`) — enum PG `session_type`.
> - Tables **plurielles** : `sessions`, `session_models` (et non `session`,
>   `authorizes`) ; PK `id` ; `teacher_id` **dérivé** via `resources.owner_id`.
> - **Une seule** limite `max_input_size` (+ `max_tokens`) au lieu de 3.
> - L'archi réelle est **MVC** : `Services\SessionService`,
>   `Models\SessionRepository`, `Domain\Session` — pas de `PdoSessionRepository`
>   ni d'interface. Source de vérité : le code +
>   [`SPEC-sessions-backend.md`](./SPEC-sessions-backend.md).

## 1. Objectifs

Une **Session** est un cours ou un examen organisé par un enseignant.
Les étudiants la rejoignent avec un **code d'accès à 6 caractères**.

> **Précision sur le code d'accès** (rapport §2.3.1, à confirmer client) —
> *« générer un code à usage unique pour que les étudiants le rejoignent ».*
> Notre interprétation actuelle : **un seul code par session**, partagé par
> toute la classe ; « usage unique » signifie « valide uniquement pour
> cette session », pas « 1 code = 1 étudiant ». Si le client demande
> finalement un code par étudiant, il faudra une table d'association
> `session_invite (session_id, student_id, code, used_at)` — cf.
> [gap-analysis](../design/gap-analysis.md).

> **Quand le code est-il généré ?** — Le code d'accès est **généré
> automatiquement au moment où la session passe en `SCHEDULED` ou
> `ACTIVE`**, pas à la création. Une session en `DRAFT` a donc
> `access_code = NULL` (la colonne est nullable, cf. `01_schema.sql`). On
> ne génère un code que lorsqu'il y a réellement quelqu'un susceptible de
> rejoindre — inutile de réserver un code pour un brouillon qui peut être
> supprimé. Implication : pas de preview de code à la création.

Une session a un **cycle de vie** :
```
DRAFT → SCHEDULED → ACTIVE → ENDED
   └────────────→ CANCELLED
```

L'enseignant peut :
- créer (avec code pré-généré, libellé, type, planification, modèles),
- modifier (tant qu'elle n'a pas commencé),
- démarrer manuellement (passe ACTIVE),
- terminer manuellement (passe ENDED),
- annuler (CANCELLED — terminal),
- consulter le dashboard.

## 2. User stories

- En tant qu'**enseignant**, je veux créer une session avec un code
  d'accès, voir le code en grand, le copier ou l'afficher en plein écran
  pour le projeter à la classe.
- En tant qu'**enseignant**, je veux choisir parmi les modèles
  disponibles ceux que les étudiants pourront utiliser.
- En tant qu'**enseignant**, je veux voir en temps réel combien
  d'étudiants sont connectés à ma session.
- En tant qu'**étudiant**, je veux rejoindre une session avec un code.

## 3. Domaine

> ⚠️ **Design hexagonal historique.** Le code réel : `Domain\Session`
> (entité riche : `fromRow`/`toRow`, `start`/`end`/`cancel`,
> `computedStatus`, `availableActions`, `rename`/`reschedule`/`reconfigure`),
> enums `Domain\SessionType` (4 valeurs) et `Domain\SessionStatus`
> (`label`/`badgeClass`/`isTerminal`). **Pas de value object `AccessCode`** :
> le code d'accès est un `?string` sur `Session`, avec les helpers statiques
> `Session::formatAccessCode()` (→ `ABC-123`) et
> `Session::normalizeAccessCode()`. **Pas d'interface de repository** : voir
> `Models\SessionRepository`.

### Entities — `App\Domain\Entities`

```php
final class Session
{
    public function __construct(
        private readonly int $id,
        private string $name,
        private SessionType $type,
        private SessionStatus $status,
        private AccessCode $accessCode,
        private ?DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $endsAt,
        private ?string $systemPromptOverride,    // pré-prompt (avant l'historique)
        private ?string $postPromptOverride,      // post-prompt (suffixe avant l'appel LLM)
        private int $maxInputSize,
        private ?string $instructions,
    ) {}

    public function rename(string $name): void;
    public function reschedule(?DateTimeImmutable $startsAt, ?DateTimeImmutable $endsAt): void;
    public function setModelsAuthorized(array $modelIds): void;  // pas vraiment ici, voir §4

    public function start(DateTimeImmutable $now): void;     // throws SessionAlreadyStarted, SessionCancelled
    public function end(DateTimeImmutable $now): void;       // throws SessionCancelled
    public function cancel(): void;                          // throws SessionAlreadyEnded

    public function canBeModified(DateTimeImmutable $now): bool;
    public function isActive(DateTimeImmutable $now): bool;
}
```

### Value Objects *(réel : enums `Domain/` + helpers sur `Session`)*

- **`SessionType`** : enum string-backed `EXAM` / `TUTORIAL` / `LAB` /
  `FREE_STUDY` (helpers `label()`, `badgeClass()`, `isExam()`).
- **`SessionStatus`** : enum `DRAFT` / `SCHEDULED` / `ACTIVE` / `ENDED` /
  `CANCELLED` (helpers `label()`, `badgeClass()`, `isTerminal()`).
- **Code d'accès** : `CHAR(6)` `[A-Z0-9]` (`ck_sessions_access_code`),
  **généré par le trigger `trg_generate_session_access_code`** au passage
  `SCHEDULED`/`ACTIVE`. Pas de VO : `?string` + `Session::formatAccessCode()`
  / `Session::normalizeAccessCode()`.

### Interfaces

```php
interface SessionRepositoryInterface
{
    public function findById(int $id): ?Session;
    public function findByAccessCode(AccessCode $code): ?Session;
    /** @return list<Session> */
    public function findAllByTeacher(int $teacherId): array;
    public function save(Session $session): void;

    /** @return list<int> */
    public function authorizedModelIdsOf(int $sessionId): array;
    public function setAuthorizedModels(int $sessionId, array $modelIds): void;

    public function generateUniqueAccessCode(): AccessCode;
}
```

### Exceptions

- `SessionNotFoundException`
- `SessionAlreadyStartedException`
- `SessionAlreadyEndedException`
- `SessionCancelledException`
- `SessionNotEditableException`

## 4. Application (use-cases)

| Service | Méthode |
|---|---|
| `CreateSessionService` | `execute(CreateSessionRequest, int $teacherId): Session` |
| `UpdateSessionService` | `execute(int $id, UpdateSessionRequest): Session` |
| `StartSessionService` | `execute(int $id): Session` |
| `EndSessionService` | `execute(int $id): Session` |
| `CancelSessionService` | `execute(int $id): Session` |
| `JoinSessionService` | `execute(AccessCode, int $userId): Session` |
| `GetSessionDashboardService` | `execute(int $id): SessionDashboardView` |
| `ListMySessionsService` | `execute(int $teacherId): SessionListView[]` |

### DTOs

```php
final class CreateSessionRequest {
    public string $name;
    public SessionType $type;
    public ?DateTimeImmutable $startsAt;
    public int $durationMinutes;          // calcul ends_at = startsAt + duration
    /** @var list<int> */ public array $modelIds;
    public ?string $systemPrompt;         // pré-prompt
    public ?string $postPrompt;           // post-prompt (rapport §2.3.1)
    public ?string $instructions;
    public int $maxInputSize;
}

final class SessionListView {
    public int $id;
    public string $name;
    public string $typeLabel;
    public string $statusLabel;
    public string $statusClass;       // 'badge-active', etc.
    public string $accessCode;
    public ?string $startsAtFormatted;
    public ?string $endsAtFormatted;
    public bool $canEdit;
    public bool $canStart;
    public bool $canEnd;
    public bool $canCancel;
}
```

> **Précision sur le code d'accès** — le repository expose
> `generateUniqueAccessCode()`. Il n'est **pas** appelé à la création :
> une session `DRAFT` n'a pas de code (cf. §1). Le code est généré par
> `StartSessionService` (passage `ACTIVE`) ou par le service qui planifie
> la session (passage `SCHEDULED`), juste avant le save, si la session
> n'en a pas déjà un. La méthode boucle jusqu'à trouver un code libre
> (collision quasi-nulle).

## 5. Infrastructure

- `PdoSessionRepository` — gère la table `session` + la table
  d'association `authorizes` (session ↔ model).
- Tâche dédiée : `generateUniqueAccessCode()` boucle jusqu'à trouver un
  code libre (probabilité de collision quasi-nulle).

## 6. HTTP

### Routes *(réellement déclarées dans [`public/index.php`](../../app/public/index.php))*

```
GET   /sessions                          SessionController::index
GET   /sessions/create                   SessionController::create
POST  /sessions/store                    SessionController::store
GET   /session/models-by-resource        SessionController::getModelsByResource  # AJAX
GET   /sessions/join                     SessionController::showJoin    # étudiant (form)
POST  /sessions/join                     SessionController::join        # étudiant
GET   /sessions/{id}/edit                SessionController::edit
POST  /sessions/{id}/update              SessionController::update
POST  /sessions/{id}/start               SessionController::start
POST  /sessions/{id}/end                 SessionController::end
POST  /sessions/{id}/cancel              SessionController::cancel
GET   /sessions/{id}/monitor             SessionController::monitor     # cf. SPEC-session-monitor
GET   /sessions/{id}/export              SessionController::export      # cf. SPEC-session-export
POST  /sessions/{id}/documents           DocumentController::uploadToSession
POST  /sessions/{id}/student-status      SessionController::setStudentActive
GET   /sessions/{id}                      SessionController::dashboard
```

> Les routes littérales sont enregistrées **avant** le wildcard `/sessions/{id}`
> pour gagner. `join` → étudiant uniquement (cf.
> [SPEC-session-chat-join](./SPEC-session-chat-join.md)).

### Views

- `pages/session/index.php` — liste : table desktop + cards mobile + section
  « Sessions surveillées » (co-supervision).
- `pages/session/create.php` — layout 2 colonnes (formulaire + preview live) ;
  **réutilisé pour l'édition** (`$mode='edit'`, type + code en lecture seule),
  il n'y a pas de `edit.php` séparé.
- `pages/session/dashboard.php` — stats + actions contextuelles + documents.
- `pages/session/monitor.php` — suivi 2 panneaux. `pages/session/join.php` —
  saisie du code étudiant.

## 7. Base de données

Source de vérité : [`01_schema.sql`](../../database/schema/01_schema.sql)
(pas de dossier `database/migrations/`). Table **`sessions`** (pluriel,
PK `id`) avec :

- `status session_status_type NOT NULL DEFAULT 'DRAFT'`
  (enum `'DRAFT','SCHEDULED','ACTIVE','ENDED','CANCELLED'`),
- `type session_type` (`'EXAM','TUTORIAL','LAB','FREE_STUDY'`),
- `access_code CHAR(6)` (`uq_sessions_access_code`,
  `ck_sessions_access_code ~ '^[A-Z0-9]{6}$'`) — **généré par le trigger**
  `trg_generate_session_access_code` au passage `SCHEDULED`/`ACTIVE`,
- `pre_prompt_override`, `post_prompt_override`, `instructions`,
  `max_input_size`, `max_tokens`, `starts_at`, `ends_at`, `closed_at`,
- `resource_id` NOT NULL → `resources(id)` (le `teacher_id` est **dérivé**
  de `resources.owner_id`, il n'y a pas de colonne `teacher_id`).

Table d'association **`session_models`** (`model_id` ↔ `session_id`) pour les
modèles autorisés. `enrollments` (`student_id`, `session_id`, `is_active`)
pour les inscriptions étudiantes.

## 8. Réutilisation POC

| Fichier POC | Action |
|---|---|
| `app/models/Session.php` | **Référence centrale.** Contient déjà `STATUS_*`, `computedStatus`, `availableActions`, `start`, `end`, `cancel`. À éclater en entité `Session` (Domain) + `PdoSessionRepository` (Infra). |
| `app/controllers/SessionController.php` | À récrire (vu les nouveaux services), mais les routes et la structure des méthodes restent. |
| `app/views/pages/session/index.php` | Réutilisable tel quel — il suffira de lui passer une `SessionListView[]` au lieu d'un array PDO + `$sessionModel` injecté. |
| `app/views/pages/session/create.php` | Idem, juste rebrancher les variables. JS preview à garder. |
| `app/views/pages/session/edit.php` | Idem. |
| `app/views/pages/session/dashboard.php` | Idem. |

> ⚠️ À ne PAS copier : l'utilisation de `extends Model` dans `Session`,
> les `Database::getInstance()->query` depuis le controller.

## 9. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Domain | `Session::start` | Lance `SessionCancelledException` si annulée. |
| Unit Domain | `AccessCode::__construct` | Lance sur `'abc'` (mauvaise longueur). |
| Unit Application | `CreateSessionService` | Calcule `ends_at` depuis `durationMinutes`. |
| Unit Application | `JoinSessionService` | Lance `SessionNotFoundException` si code inconnu. |
| Integration | `PdoSessionRepository::generateUniqueAccessCode` | Sur 1000 itérations, aucun doublon. |
| Acceptance | `POST /sessions/store` connecté en teacher | Crée + redirect /sessions. |

## 10. Anti-patterns spécifiques

- ❌ Calculer le statut "ACTIVE" en regardant des `if (now > starts_at)`
  dans la vue ou le controller. On dérive ça **une fois** dans
  `Session::computedStatus()` (méthode du Domain).
- ❌ Stocker `duration_min` en DB (pas dans le schéma actuel) — on
  calcule `ends_at = starts_at + duration_min * 60` à la création.
- ❌ Passer un `array` venant de PDO directement à la vue. Toujours via
  `SessionListView` / `SessionDashboardView`.
- ❌ Régénérer un nouveau code d'accès à chaque GET sur `/sessions/{id}/edit`
  (bug remarqué dans le POC). Une fois généré (passage SCHEDULED/ACTIVE),
  le code est dans `$session`, c'est tout.
- ❌ Générer le code dès la création / en DRAFT. Le code n'apparaît qu'au
  passage SCHEDULED ou ACTIVE (cf. §1).

---

## 11. Évolutions could-have (rapport §2.3.3)

Pas implémentées dans cette spec mais à anticiper :

### 11.1 Upload de documents par l'enseignant

Le rapport mentionne *« chargement de document »* dans les contraintes
applicables à un modèle (besoins fonctionnels enseignant). Deux niveaux
possibles :

- **Léger** : l'enseignant joint un texte (PDF/MD) qui est concaténé au
  pré-prompt. Suffit pour des consignes longues.
- **RAG** : indexation du document + recherche sémantique au moment du
  prompt. Bien plus complexe, dépend d'un vector store.

→ Recommandation : version légère d'abord
(`session.attached_document_text TEXT`).

> 📄 **Détaillé en 3 phases** (gestion de fichiers → import chat → RAG) dans
> [`SPEC-documents-rag.md`](./SPEC-documents-rag.md).

### 11.2 Vérification IP en examen

Le rapport mentionne *« vérification des IP lors des examens pour
limiter les possibilités de triche »*. Voir
[spec 04-supervise §11](./04-supervise.md) qui en hérite naturellement
(la session est l'agrégat parent).

### 11.3 Code d'accès par étudiant

Si le client clarifie en faveur de codes individuels, voir
[gap-analysis](../design/gap-analysis.md) — table
`session_invite` à introduire.
