# Spec 04 — Supervise

## 0. Statut
- **Priorité** : nice-to-have (gros impact UX enseignant)
- **Dépend de** : 02-sessions, 03-chat-llm
- **État POC** : implémenté

## 1. Objectifs

Donner à l'enseignant une **vue temps quasi-réel** de ce qu'écrivent ses
étudiants pendant une session de cours/examen :
- liste des étudiants avec leur dernier prompt,
- onglets (ouvertes / rendues / signalées / toutes),
- détail d'une conversation au clic,
- possibilité de **signaler** un prompt (contournement de consigne, hors
  sujet, etc.) et d'y attacher un commentaire de marge visible à la
  fermeture,
- archivage massif de la session ("toute la classe a rendu").

## 2. User stories

- En tant qu'**enseignant**, je veux voir en direct ce que chacun de
  mes 27 étudiants demande au LLM, sans rafraîchir la page sans cesse.
- En tant qu'**enseignant**, je veux signaler un prompt problématique
  et y attacher un mot pour l'étudiant ("tu contournes la consigne").
- En tant qu'**enseignant**, à la fin du cours, je veux archiver toute
  la session en un clic pour figer les conversations.

## 3. Domaine

### Entities étendues (déjà introduites en spec 03)

- `Interaction` gagne **`teacherFlag: TeacherFlag`** (déjà prévu en
  spec 03).
- `Conversation` gagne **`submittedAt: ?DateTimeImmutable`** (déjà
  prévu).

### Value Object

```php
final class TeacherFlag
{
    public function __construct(
        public readonly bool $flagged,
        public readonly ?string $reason,    // 'contourne-consigne', 'hors-sujet', etc.
        public readonly ?string $comment,
    ) {}

    public static function none(): self { return new self(false, null, null); }
    public function raise(string $reason, ?string $comment): self;
    public function clear(): self;
}
```

### Interfaces (étendues)

```php
interface SessionSupervisionRepositoryInterface
{
    /**
     * Liste structurée des étudiants d'une session avec leur dernier prompt,
     * leur statut (ouverte/rendue/signalée), pour rendre la sidebar de gauche.
     *
     * @return list<SupervisionStudentRow>
     */
    public function supervisionRowsForSession(int $sessionId): array;
}
```

### DTOs (Application)

```php
final class SupervisionStudentRow {
    public int $conversationId;
    public int $userId;
    public string $firstName;
    public string $lastName;
    public ?string $studentNumber;
    public ?string $lastPrompt;
    public ?DateTimeImmutable $lastAt;
    public ?string $lastModelTag;
    public int $promptCount;
    public int $flagCount;
    public ?DateTimeImmutable $submittedAt;
}

final class SupervisionView {
    /** @var list<SupervisionStudentRow> */
    public array $students;
    public int $studentsCount;
    public int $submittedCount;
    public int $flaggedCount;
    public ?ConversationDetailView $selected;
}

final class ConversationDetailView {
    public int $conversationId;
    public string $studentFullName;
    public ?string $studentNumber;
    /** @var list<InteractionTurnView> */
    public array $turns;          // alternance élève/LLM
    public ?string $flagReason;   // 1re raison non vide trouvée
}
```

## 4. Application (use-cases)

| Service | Méthode |
|---|---|
| `GetSupervisionViewService` | `execute(int $sessionId, ?int $selectedConversationId, string $tab): SupervisionView` |
| `FlagPromptService` | `execute(int $interactionId, string $reason, ?string $comment)` |
| `ClearPromptFlagService` | `execute(int $interactionId)` |
| `ArchiveSessionService` | `execute(int $sessionId): int` — passe toutes les conv à `submitted_at = NOW`, retourne le nombre. |

> Les filtres par onglet (`open`, `submitted`, `flagged`, `all`) sont
> appliqués côté Application sur le résultat de `supervisionRowsForSession`.

## 5. Infrastructure

- `PdoSessionSupervisionRepository implements SessionSupervisionRepositoryInterface`.
- La requête principale agrège en une passe :
  - tous les `conversation` de la session,
  - jointure avec `"user"`, `student`,
  - sous-requêtes corrélées pour : dernier prompt, dernier `sent_at`,
    dernier modèle, nb total de prompts, nb signalés.

> La requête existe déjà en POC dans
> `git show poc:app/controllers/ExamController.php` (méthode `supervise`).
> À extraire telle quelle dans le repository.

## 6. HTTP

### Routes

```
GET   /exam/{id}/supervise               ExamController::supervise
POST  /exam/flag                         ExamController::flagPrompt
POST  /exam/unflag                       ExamController::unflagPrompt
POST  /exam/{id}/archive                 ExamController::archiveSession
GET   /exam/{id}/poll                    ExamController::poll          # AJAX live update
```

### Views

- `exam/supervise.php` — split-view :
  - **gauche** : recherche + onglets + tableau étudiants,
  - **droite** : conversation détaillée + form signalement.

## 7. Base de données

Migration (déjà appliquée en POC) :

```sql
ALTER TABLE interaction
    ADD COLUMN teacher_flag        SMALLINT DEFAULT 0,
    ADD COLUMN teacher_flag_reason VARCHAR(100),
    ADD COLUMN teacher_comment     TEXT;

CREATE INDEX idx_interaction_teacher_flag
    ON interaction (teacher_flag)
    WHERE teacher_flag <> 0;

ALTER TABLE conversation
    ADD COLUMN submitted_at TIMESTAMP;
```

Fichier : `database/migrations/AAAA-MM-DD-teacher-supervision.sql`.

## 8. Réutilisation POC

| Fichier POC | Action |
|---|---|
| `app/controllers/ExamController.php` | La méthode `supervise()` contient la requête agrégée. À déplacer dans `PdoSessionSupervisionRepository`. `flagPrompt` et `archiveSession` deviennent des services applicatifs. |
| `app/views/pages/exam/supervise.php` | Réutilisable. On lui passe une `SupervisionView`. |
| `database/migrations/2026-05-26-teacher-supervision.sql` | Déjà existant — à appliquer aussi en dev. |

## 9. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Domain | `TeacherFlag::raise` | Renvoie une nouvelle instance immuable. |
| Unit Application | `FlagPromptService` | Mocke `InteractionRepositoryInterface` + un cas où la conv n'appartient pas à la session → exception. |
| Unit Application | `GetSupervisionViewService::execute('flagged')` | Filtre correctement. |
| Integration | `PdoSessionSupervisionRepository` | Avec 3 étudiants dont 1 signalé : `flag_count` correct. |
| Acceptance | `GET /exam/{id}/supervise?tab=flagged` connecté en teacher | HTML contient les bons étudiants. |

## 10. Anti-patterns spécifiques

- ❌ Calculer `flag_count` par étudiant côté PHP en bouclant sur les
  interactions. C'est le rôle d'une sous-requête SQL.
- ❌ Mettre la logique d'agrégation supervision dans le controller (cf.
  POC initial). Tout dans le repository.
- ❌ Faire du polling AJAX trop agressif (< 5 s). Préférer 5 s minimum,
  ou un Server-Sent Event si le besoin existe.
- ❌ Stocker la `reason` en texte libre sans énumération. Préférer une
  liste fermée côté UI (`'contourne-consigne'`, `'hors-sujet'`,
  `'qualite-faible'`, `'autre'`).
