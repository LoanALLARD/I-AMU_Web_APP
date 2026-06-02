# Spec 03 — Chat & LLM

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations, 01-auth-account, 02-sessions
- **État POC** : implémenté (mais couplé à Ollama — à abstraire)

## 1. Objectifs

Permettre à un utilisateur d'avoir une conversation avec un modèle LLM,
en streaming (réponse mot par mot), avec :
- soit un **mode libre** (`type = FREE`, hors session),
- soit un **mode session** (`type = COURSE` ou `EXAM`, scopé à une session
  avec ses contraintes : modèles autorisés, pré-prompt, taille max).

Le tout doit être **agnostique du provider LLM** : Ollama aujourd'hui,
OpenAI ou Anthropic demain — sans changer le métier.

## 2. User stories

- En tant qu'**étudiant**, je veux discuter librement avec un LLM,
  garder l'historique de mes conversations, en créer plusieurs en
  parallèle, et les archiver.
- En tant qu'**étudiant**, je veux voir la réponse arriver mot par mot
  (streaming), pour me sentir en discussion.
- En tant qu'**étudiant** dans une session de cours, je veux n'avoir
  accès qu'aux modèles autorisés par l'enseignant.
- En tant qu'**enseignant**, je veux que les modèles disponibles soient
  ceux réellement installés sur le serveur Ollama (sync automatique).

## 3. Domaine

### Entities — `App\Domain\Entities`

```php
final class Conversation
{
    public function __construct(
        private readonly int $id,
        private string $name,
        private ConversationType $type,        // FREE / COURSE / EXAM
        private readonly int $userId,
        private readonly ?int $sessionId,
        private bool $isArchived,
        private ?DateTimeImmutable $submittedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public function rename(string $name): void;
    public function archive(): void;
    public function submit(DateTimeImmutable $now): void;
    public function belongsTo(int $userId): bool;
}

final class Interaction          // 1 interaction = 1 prompt + 1 response
{
    public function __construct(
        private readonly int $id,
        private readonly int $conversationId,
        private readonly int $modelId,
        private string $prompt,
        private ?string $response,
        private int $inputTokens,
        private int $outputTokens,
        private int $latencyMs,
        private ?int $userFeedback,             // -1, 0, 1
        private TeacherFlag $teacherFlag,       // VO : ok / signalé + raison + commentaire
        private readonly DateTimeImmutable $sentAt,
    ) {}

    public function rateByUser(int $score): void;
    public function flagByTeacher(string $reason, ?string $comment): void;
    public function clearFlag(): void;
}

final class LlmModel             // métadonnées d'un modèle, pas le LLM lui-même
{
    public function __construct(
        private readonly int $id,
        private string $name,              // tag Ollama exact (ex: 'llama3:8b')
        private ?string $version,
        private string $provider,
        private int $maxTokens,
        private int $contextWindow,
        private bool $isActive,
    ) {}

    public function activate(): void;
    public function deactivate(): void;
    public function tag(): string { return $this->name; }
}
```

### Value Objects

- **`ConversationType`** : enum (`Free`, `Course`, `Exam`).
- **`TeacherFlag`** : `flagged: bool`, `reason: ?string`, `comment: ?string`.
- **`LlmResponse`** : DTO retourné par le provider (`response: string`,
  `inputTokens: int`, `outputTokens: int`, `latencyMs: int`).
- **`LlmModelInfo`** : DTO retourné par `listModels()`
  (`tag: string`, `version: ?string`, `details: array`).

### Interfaces

```php
interface ConversationRepositoryInterface {
    public function findById(int $id): ?Conversation;
    public function findOpenForSession(int $sessionId): array;
    /** @return list<Conversation> */
    public function findByUser(int $userId, bool $includeArchived = false): array;
    public function save(Conversation $c): void;
}

interface InteractionRepositoryInterface {
    public function findById(int $id): ?Interaction;
    /** @return list<Interaction> */
    public function findByConversation(int $conversationId): array;
    public function save(Interaction $i): void;
}

interface LlmModelRepositoryInterface {
    public function findById(int $id): ?LlmModel;
    public function findByTag(string $tag): ?LlmModel;
    /** @return list<LlmModel> */
    public function findActive(): array;
    public function save(LlmModel $model): void;
}
```

### Port (interface d'adapter externe)

```php
namespace App\Application\Ports;

interface LlmProviderInterface
{
    /** @param list<array{role:string,content:string}> $messages */
    public function chat(string $modelTag, array $messages, ?string $system = null): LlmResponse;

    /** @param callable(string $chunk): void $onChunk */
    public function chatStream(string $modelTag, array $messages, ?string $system, callable $onChunk): LlmResponse;

    /** @return list<LlmModelInfo> */
    public function listModels(): array;

    public function isAvailable(): bool;
}
```

## 4. Application (use-cases)

| Service | Méthode |
|---|---|
| `StartConversationService` | `execute(int $userId, string $name, ConversationType, ?int $sessionId): Conversation` |
| `SendStudentPromptService` | `execute(SendPromptRequest): Interaction` (mode synchrone) |
| `StreamStudentPromptService` | `execute(SendPromptRequest, callable $onChunk): Interaction` (mode SSE) |
| `RateInteractionService` | `execute(int $interactionId, int $userId, int $score)` |
| `ArchiveConversationService` | `execute(int $conversationId, int $userId)` |
| `SyncOllamaModelsService` | `execute(): SyncResult` — appelle `listModels()` puis met à jour `LlmModelRepositoryInterface`. |
| `CheckOllamaStatusService` | `execute(): OllamaStatusView` — pour la pastille temps réel. |

### DTOs

```php
final class SendPromptRequest {
    public int $conversationId;
    public int $modelId;
    public string $prompt;
    public int $userId;
}

final class SyncResult {
    public int $added;
    public int $reactivated;
    public int $disabled;
}
```

### Règles métier critiques

- **Tag du modèle** : le `name` d'un `LlmModel` **doit être le tag Ollama
  exact** (ex: `llama3:8b`, `kimi-k2.6:cloud`). Pas de `strtolower`, pas
  d'espace, pas de transformation. Sinon → 404 côté Ollama.
- **Mode session** : si la conversation est liée à une session, vérifier
  que :
  - la session est ACTIVE,
  - le modèle est dans `authorizes` de la session,
  - la longueur du prompt ≤ `session.max_input_size`,
  - et si type=EXAM : aucune autre conversation ouverte du user pour cette session.
- **Cache sync** : `SyncOllamaModelsService` peut être appelé à chaque
  visite du chat ; il garde un timestamp dans un fichier temp et ne
  re-sync que toutes les 5 minutes.

## 5. Infrastructure

- `PdoConversationRepository`, `PdoInteractionRepository`,
  `PdoLlmModelRepository`.
- `OllamaLlmProvider implements LlmProviderInterface` — utilise cURL
  pour `POST /api/chat`, `GET /api/tags`.
- `FakeLlmProvider` — pour les tests : retourne une réponse fixe.

## 6. HTTP

### Routes

```
GET   /chat                          ChatController::index
GET   /chat/{id}                     ChatController::show
POST  /chat/create                   ChatController::createConversation
POST  /chat/send                     ChatController::sendPrompt
POST  /chat/stream                   ChatController::sendPromptStream     # SSE
POST  /chat/{id}/archive             ChatController::archive
GET   /chat/ollama/status            ChatController::ollamaStatus         # AJAX, pour la pastille navbar
```

### Views

- `chat/index.php` — sidebar conversations + zone de chat + select modèle.
- Markdown render côté client (`marked.js` chargé en CDN — à conserver).

### Streaming SSE

Le controller `sendPromptStream` ouvre un flux SSE et appelle
`StreamStudentPromptService` avec une closure qui fait :

```php
echo "event: chunk\ndata: " . json_encode(['text' => $chunk]) . "\n\n";
ob_flush(); flush();
```

> ⚠️ Apache requiert `output_buffering = Off` et le header
> `X-Accel-Buffering: no` (déjà fait dans `docker/apache.conf` du POC).

## 7. Base de données

Tables existantes : `conversation`, `interaction`, `model`, `authorizes`.
Pas de migration nouvelle dans cette spec.

## 8. Réutilisation POC

| Fichier POC | Action |
|---|---|
| `app/services/OllamaService.php` | Devient `OllamaLlmProvider`. **Bug à corriger** : retirer `strtolower($model)` qui casse les tags. |
| `app/models/Conversation.php` | Hydratation à reprendre dans `PdoConversationRepository`. |
| `app/models/Interaction.php` | Idem. |
| `app/models/LlmModel.php` | Idem. La méthode `syncFromOllama` devient `SyncOllamaModelsService`. |
| `app/controllers/ChatController.php` | Référence pour les routes et le streaming. À récrire en délégant aux services. |
| `app/views/pages/chat/index.php` | Réutilisable, on lui passe des ViewModels. |
| `public/assets/js/app.js` | Le code SSE côté client reste tel quel. |

## 9. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Domain | `Interaction::flagByTeacher` | Stocke raison + comment. |
| Unit Application | `SendStudentPromptService` avec `FakeLlmProvider` | Persiste l'interaction avec les bons tokens/latency. |
| Unit Application | `SyncOllamaModelsService` | Ajoute, réactive, désactive correctement selon le diff. |
| Integration | `OllamaLlmProvider::listModels` contre un container Ollama de test | Retourne la liste. |
| Acceptance | `POST /chat/send` | 200 + JSON contenant `response`, `latency`. |

## 10. Anti-patterns spécifiques

- ❌ `OllamaLlmProvider` instancié directement dans `ChatController`.
  Toujours via l'interface.
- ❌ `Database::getInstance()` dans le streaming. Le service reçoit ses
  repositories en constructeur.
- ❌ Construire le payload Ollama dans le controller. C'est dans le
  provider.
- ❌ Renvoyer un row PDO brut à la vue chat. ViewModels obligatoires
  pour les listes de conversations.
- ❌ Hardcoder des modèles dans le seed SQL (cf. POC initial qui avait
  `'Mistral'`, `'Llama 3'` — noms qui ne correspondent à aucun tag
  Ollama).
