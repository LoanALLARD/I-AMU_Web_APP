# Spec 02 — Sessions

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations, 01-auth-account
- **État POC** : implémenté (lifecycle déjà bien modélisé)

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
> [gap-analysis](../documentation/gap-analysis.md).

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

### Value Objects

- **`SessionType`** : enum (`TP`, `EXAM`, `SANDBOX`).
- **`SessionStatus`** : enum (`Draft`, `Scheduled`, `Active`, `Ended`,
  `Cancelled`).
- **`AccessCode`** : 6 caractères `[A-Z0-9]`, auto-validation.

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
> `generateUniqueAccessCode()`. Le controller `create` l'appelle pour
> afficher un code en preview, et le passe en hidden au POST. Le service
> `CreateSessionService` reçoit ce code candidat ; s'il est encore
> disponible au moment du save, il est conservé, sinon un nouveau est
> généré.

## 5. Infrastructure

- `PdoSessionRepository` — gère la table `session` + la table
  d'association `authorizes` (session ↔ model).
- Tâche dédiée : `generateUniqueAccessCode()` boucle jusqu'à trouver un
  code libre (probabilité de collision quasi-nulle).

## 6. HTTP

### Routes

```
GET   /sessions                       SessionController::index
GET   /sessions/create                SessionController::create
POST  /sessions/store                 SessionController::store
GET   /sessions/{id}/edit             SessionController::edit
POST  /sessions/{id}/update           SessionController::update
POST  /sessions/{id}/cancel           SessionController::cancel
POST  /sessions/{id}/start            SessionController::start
POST  /sessions/{id}/end              SessionController::end
GET   /sessions/{id}                  SessionController::dashboard
POST  /sessions/join                  SessionController::join     # étudiant
```

### Views

- `session/index.php` — liste : table desktop + cards mobile + menu 3 points.
- `session/create.php` — layout 2 colonnes (formulaire + preview live).
- `session/edit.php` — réutilise le layout de create, code en lecture seule.
- `session/dashboard.php` — stats + barre d'actions contextuelles.

## 7. Base de données

Table `session` (existante) avec colonne `status` (enum
`session_status`) ajoutée au POC.

```sql
-- database/migrations/AAAA-MM-DD-session-status.sql (déjà appliqué en POC)
CREATE TYPE session_status AS ENUM
    ('DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED', 'CANCELLED');

ALTER TABLE session ADD COLUMN status session_status NOT NULL DEFAULT 'SCHEDULED';

-- Nouveau : post-prompt
ALTER TABLE session ADD COLUMN IF NOT EXISTS post_prompt_override TEXT;
```

Table `authorizes` (existante) : `session_id` ↔ `model_id`.

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
  (bug remarqué dans le POC). Le code est dans `$session`, c'est tout.

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

### 11.2 Vérification IP en examen

Le rapport mentionne *« vérification des IP lors des examens pour
limiter les possibilités de triche »*. Voir
[spec 04-supervise §11](./04-supervise.md) qui en hérite naturellement
(la session est l'agrégat parent).

### 11.3 Code d'accès par étudiant

Si le client clarifie en faveur de codes individuels, voir
[gap-analysis](../documentation/gap-analysis.md) — table
`session_invite` à introduire.
