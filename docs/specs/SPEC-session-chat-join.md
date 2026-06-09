# SPEC: session-chat-join — Lier le chat aux sessions à l'inscription

> Date: 2026-06-02 | Branche: `Sessions` | Statut: **IMPLÉMENTÉ** (puis refondu en MVC)

> ⚠️ **Recadrage 2026-06-09.** La fonctionnalité **est livrée**, mais le code a
> depuis basculé en **MVC + Domain** : le plan ci-dessous (rédigé en Clean
> Archi) ne reflète plus les noms réels. Correspondance :
> - `Application\Services\JoinSessionService` → **`Services\SessionService::join(string $rawCode, int $studentUserId): array`**
>   (renvoie `['conversationId', 'alreadyJoined', 'sessionName']`).
> - `Domain\Repositories\ConversationRepositoryInterface` /
>   `Infrastructure\…\PdoConversationRepository` → **`Models\ConversationRepository`**
>   (`findIdByUserAndSession`, `newConversation`, …). **Pas d'interface.**
> - `Domain\Repositories\EnrollmentRepositoryInterface` /
>   `PdoEnrollmentRepository` → **`Models\EnrollmentRepository`**
>   (`exists`, `enroll`, `isActive`, `setActive`).
> - DTOs `ConversationView` / `JoinSessionResult` → **tableaux** simples.
> - Vues : **`pages/home.php`** + **`layout/chat.php`** (pas `homeView.php` /
>   `Layout/chat.php` ; le doublon `Views/Page/homeView.php` a été supprimé).
> - **Nom de conversation réel** : `"SESSION - {CODE} #{n}"`
>   (`ChatService::newSessionConversation`), pas `"SESSION - {code formaté}"`.
> - `AccessCode::fromUserInput()` / `->formatted()` →
>   `Session::normalizeAccessCode()` / `Session::formatAccessCode()`.
> Le reste est conservé comme trace de conception.

## Écarts d'implémentation (Phase 4)

- **Interfaces dans `Application\Ports`** (pas `Domain\Repositories`) : `ConversationRepositoryInterface` retourne un DTO Application (`ConversationView`) → le Domain ne peut pas en dépendre. Même précédent que `ModelReadRepositoryInterface`. `EnrollmentRepositoryInterface` y est co-localisé par cohésion.
- **`JoinSessionResult` porte un 3ᵉ champ `sessionName`** (au-delà de `conversationId` + `alreadyJoined`) — permet au controller de formuler le flash sans requête supplémentaire.
- **`INSERT ... RETURNING id`** utilisé (au lieu de `lastInsertId`) pour récupérer l'id, comme les autres repos.

## Contexte

Quand un étudiant rejoint une session avec le code d'accès, on doit :
1. l'**enrôler** dans la session,
2. créer la **conversation** associée, nommée `SESSION - {code}` (ex. `SESSION - N80-RF1`),
3. le **rediriger** vers le chat lié à cette conversation.

Un étudiant ne peut rejoindre **qu'une seule fois** la même session : une 2ᵉ tentative
**rouvre sa conversation existante** (idempotent), sans en créer une nouvelle.

**Portée de cette spec : liaison seule.** Le chat fonctionne comme aujourd'hui (modèle
libre, appel LLM direct) — la persistance des messages dans `interactions`, la restriction
aux `session_models` et l'injection des pré/post-prompts sont **hors périmètre** (spec 03).

## Décisions (validées avec l'utilisateur)

| Question | Décision |
|---|---|
| Nom de la conversation | `SESSION - {code d'accès formaté}` (ex. `SESSION - N80-RF1`) |
| Re-join (déjà inscrit) | **Idempotent** : rediriger vers la conversation existante, pas d'erreur |
| Portée | **Liaison seule** : enrôlement + conversation + redirection |
| UI étudiant | **Oui**, une UI minimale de saisie de code |

## Diagnostic (Phase 1)

| Règle | Statut | Détail | Source |
|---|---|---|---|
| `enrollments` PK `(student_id, session_id)` | OK | Le « rejoindre 1 fois » est **déjà garanti par la DB**. FK `student_id → students` ⇒ étudiants only | `database/schema/01_schema.sql` |
| `conversations` | OK | `user_id` NN, `session_id` **nullable**, `name` NN, `is_archived`. Pas d'unicité (user, session) | table live |
| `session_models` | OK | Modèles autorisés par session (non utilisé ici — spec 03) | table live |
| `JoinSessionService` | Partiel | Valide code + statut ACTIVE, **ne crée rien** | `app/src/Application/Services/JoinSessionService.php:36` |
| `SessionController::join` | Partiel | Flash + redirect `/`, aucune conversation | `app/src/Http/Controllers/SessionController.php` |
| Repos chat dev | À remplacer | `Models\ConversationRepository` (legacy, PDO brut, `namespace Models`) | `app/src/Models/ConversationRepository.php` |
| UI de saisie de code | KO | N'existe pas (le code n'apparaît que côté prof) | grep |
| Router params `{id}` | OK | Supporté ; `/sessions/create` est déjà placé avant `/sessions/{id}` | `app/public/index.php:113` |
| Doublon de vue | À nettoyer | Le merge dev a ressuscité `app/src/Views/Page/homeView.php` (doublon mort) | ls |

**Cause racine** : le join est un cul-de-sac (valide puis renvoie à l'accueil). La plomberie DB
(`enrollments`, `conversations`) existe mais rien ne la relie, et l'étudiant n'a aucun moyen de
saisir un code.

## Plan d'actions

### Backend — Domain (interfaces)

- [ ] **B1** — Créer `ConversationRepositoryInterface` (port Domain).
  - Méthodes : `findIdByUserAndSession(int $userId, int $sessionId): ?int`, `create(int $userId, int $sessionId, string $name): int`, `findOwnedById(int $conversationId, int $userId): ?ConversationView`.
  - Fichier : `app/src/Domain/Repositories/ConversationRepositoryInterface.php`
- [ ] **B2** — Créer `EnrollmentRepositoryInterface` (port Domain).
  - Méthodes : `exists(int $studentId, int $sessionId): bool`, `enroll(int $studentId, int $sessionId): void`.
  - Fichier : `app/src/Domain/Repositories/EnrollmentRepositoryInterface.php`

### Backend — Application

- [ ] **B3** — Créer le DTO de sortie `ConversationView` (id, name, sessionId).
  - Fichier : `app/src/Application/DTOs/ConversationView.php`
- [ ] **B4** — Créer le DTO de résultat `JoinSessionResult` (conversationId, alreadyJoined).
  - Fichier : `app/src/Application/DTOs/JoinSessionResult.php`
- [ ] **B5** — Étendre `JoinSessionService` : après validation, enrôler (si absent) puis find-or-create la conversation `SESSION - {code}` ; retourner `JoinSessionResult`.
  - Injecte `ConversationRepositoryInterface` + `EnrollmentRepositoryInterface`.
  - Nom = `'SESSION - ' . $session->accessCode()->formatted()`.
  - Idempotent : `findIdByUserAndSession` d'abord ; `create` seulement si null. `alreadyJoined = enrollment->exists(...)` avant enrôlement.
  - Fichier : `app/src/Application/Services/JoinSessionService.php`

### Backend — Infrastructure

- [ ] **B6** — Créer `PdoConversationRepository` (implémente B1, étend `PdoRepository`).
  - Fichier : `app/src/Infrastructure/Persistence/PdoConversationRepository.php`
- [ ] **B7** — Créer `PdoEnrollmentRepository` (implémente B2, étend `PdoRepository`).
  - `enroll` : `INSERT INTO enrollments (student_id, session_id) VALUES (...) ON CONFLICT DO NOTHING`.
  - Fichier : `app/src/Infrastructure/Persistence/PdoEnrollmentRepository.php`

### Backend — Http

- [ ] **B8** — `SessionController::join` : exiger le rôle `student`, appeler le service, rediriger vers `/chat/{conversationId}`, flash selon `alreadyJoined`.
  - Fichier : `app/src/Http/Controllers/SessionController.php`
- [ ] **B9** — `SessionController::showJoin` (GET) : rendre le formulaire `pages/session/join`.
  - Fichier : `app/src/Http/Controllers/SessionController.php`
- [ ] **B10** — `ChatController::index(?string $conversationId = null)` : si id fourni, charger la conversation via `findOwnedById` (contrôle de propriété), passer `name`/`conversation` à la vue ; sinon comportement actuel.
  - Fichier : `app/src/Http/Controllers/ChatController.php`
- [ ] **B11** — Routes dans `index.php` : ajouter `GET /sessions/join` (**avant** `GET /sessions/{id}`) et `GET /chat/{id}` ; câbler les 2 nouveaux repos + injecter `ConversationRepositoryInterface` dans `ChatController`.
  - Fichier : `app/public/index.php`

### Frontend — Vues + JS

- [ ] **F1** — Créer `pages/session/join.php` : carte centrée, input « code d'accès » **avec `id="join-code-input"`** (requis par `session-join.js`), CSRF, bouton « Rejoindre ». Charger `session-join.js`.
  - Fichier : `app/src/Views/pages/session/join.php`
- [ ] **F2** — `Layout/chat.php` (ligne 82) : afficher `htmlspecialchars($conversation['name'] ?? 'Nouvelle conversation')` dans `#convName` au lieu du texte en dur. (Le nom de conv vit dans le **layout**, pas dans `homeView`.)
  - Fichier : `app/src/Views/Layout/chat.php`
- [ ] **F3** — `homeView.php` : ajouter un point d'entrée « Rejoindre une session » pour les étudiants (lien vers `/sessions/join`) dans l'empty-state.
  - Fichier : `app/src/Views/pages/homeView.php`

### Nettoyage (lié, hors logique métier)

- [ ] **N1** — Supprimer le doublon mort `app/src/Views/Page/homeView.php` (le merge dev l'a ressuscité ; `ChatController` rend `pages/homeView`).
  - Fichier : `app/src/Views/Page/homeView.php`

## Flux cible (POST /sessions/join)

```
requireRole('student') + verifyCsrf
  └─ JoinSessionService::execute(rawCode, userId)
       1. AccessCode::fromUserInput → findByAccessCode → statut ACTIVE  (existant)
       2. alreadyJoined = enrollments.exists(userId, sessionId)
       3. si !alreadyJoined : enrollments.enroll(userId, sessionId)
       4. convId = conversations.findIdByUserAndSession(userId, sessionId)
                   ?? conversations.create(userId, sessionId, "SESSION - {code}")
       5. return JoinSessionResult(convId, alreadyJoined)
  └─ redirect /chat/{convId}  (+ flash selon alreadyJoined)
```

`student_id` (enrollments) et `user_id` (conversations) ont la même valeur numérique
(héritage vertical `students.id = users.id`), donc `currentUser()['id']` sert pour les deux.

## Fichiers concernés

### À modifier
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Application/Services/JoinSessionService.php` | Orchestration enrôlement + conversation | B5 |
| `app/src/Http/Controllers/SessionController.php` | join + showJoin | B8, B9 |
| `app/src/Http/Controllers/ChatController.php` | charger une conversation par id, passer `conversation` au render | B10 |
| `app/public/index.php` | routes + DI | B11 |
| `app/src/Views/Layout/chat.php` | `#convName` dynamique (ligne 82) | F2 |
| `app/src/Views/pages/homeView.php` | entrée « Rejoindre » pour étudiants | F3 |

### À créer
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Domain/Repositories/ConversationRepositoryInterface.php` | port | B1 |
| `app/src/Domain/Repositories/EnrollmentRepositoryInterface.php` | port | B2 |
| `app/src/Application/DTOs/ConversationView.php` | DTO sortie | B3 |
| `app/src/Application/DTOs/JoinSessionResult.php` | DTO résultat | B4 |
| `app/src/Infrastructure/Persistence/PdoConversationRepository.php` | impl | B6 |
| `app/src/Infrastructure/Persistence/PdoEnrollmentRepository.php` | impl | B7 |
| `app/src/Views/pages/session/join.php` | UI saisie code | F1 |

### À réutiliser (modèle existant)
| Fichier:ligne | Élément | Pour |
|---|---|---|
| `app/src/Infrastructure/Persistence/PdoModelRepository.php` | patron `extends PdoRepository` + `fetchOne/fetchAll` | B6, B7 |
| `app/public/assets/js/session-join.js` | auto-majuscule + tiret du champ code | F1 |
| `app/src/Views/pages/session/create.php` | markup `access-code` + CSRF + boutons | F1 |
| `PdoConnection::pdo()` / `lastInsertId('conversations_id_seq')` | id généré après INSERT | B6 |

### À supprimer (remplacé / mort)
| Fichier | Raison |
|---|---|
| `app/src/Models/ConversationRepository.php` | remplacé par `PdoConversationRepository` (Clean Archi) |
| `app/src/Views/Page/homeView.php` | doublon mort ressuscité par le merge (N1) |

> `Models\InteractionRepository` et `Models\UserRepository` (dev) **restent** : ils servent à la
> spec 03 (persistance des messages), hors périmètre ici.

## Impacts

- [x] **Permissions** : join réservé au rôle `student` (FK `enrollments.student_id → students`). Un enseignant qui tente de rejoindre → flash d'erreur.
- [x] **Sécurité** : POST via `csrf_field()` + `verifyCsrf()`. Chargement d'une conversation = contrôle de propriété (`findOwnedById(convId, userId)`). Requêtes préparées only.
- [x] **Consumers** : `ChatController::index` gagne un paramètre **optionnel** → la route `GET /chat` actuelle reste inchangée.
- [x] **Multi-tenant** : N/A (single-tenant).
- [x] **DB** : aucune migration nécessaire (tables `enrollments`/`conversations` déjà en place).

## Questions ouvertes

- **Unicité conversation (durcissement)** : faut-il ajouter un index unique `uq_conversations_user_session` (partiel, `WHERE session_id IS NOT NULL`) pour garantir au niveau DB « 1 conversation par (user, session) » ? Aujourd'hui c'est garanti applicativement (find-or-create) + par la PK `enrollments`. **Proposition** : optionnel, à ajouter plus tard.
- **Point d'entrée UI** : le bouton « Rejoindre une session » sur l'accueil chat (F2) suffit-il, ou veux-tu aussi un onglet dédié dans le topbar pour les étudiants ?

## Réutilisation (Phase 3 — vérifiée)

### Réutilisé tel quel (existe et confirmé par grep)
| Élément | Fichier:ligne | Pour |
|---|---|---|
| `session-join.js` (auto-maj + tiret, cible `#join-code-input`) | `app/public/assets/js/session-join.js` | F1 — le champ **doit** avoir `id="join-code-input"` |
| Helpers `requireRole/requireAuth/currentUser/input/redirect/flash/verifyCsrf/render` | `app/src/Core/Controller.php:35-179` | B8, B9, B10 |
| `input()` lit **`$_POST`** uniquement | `Core/Controller.php:109` | B8 (POST join OK) ; `/chat/{id}` passe par le **param de route**, pas `input()` |
| Router param `{id}` (modèle `/sessions/{id}`) | `app/public/index.php:129` | B11 — `GET /chat/{id}` sans conflit avec `GET`/`POST /chat` |
| `findByAccessCode(AccessCode): ?Session` | `Domain/Repositories/SessionRepositoryInterface.php:27` | B5 (déjà appelé par le service actuel) |
| `Session::accessCode()` + `AccessCode::formatted()` | `Session.php:77`, `AccessCode.php:38` | B5 — nom = `'SESSION - ' . $session->accessCode()->formatted()` |
| Patron `extends PdoRepository` + `fetchOne/fetchAll` | `Infrastructure/Persistence/PdoModelRepository.php` | B6, B7 |
| `PdoConnection::pdo()` / `lastInsertId('conversations_id_seq')` | `Infrastructure/Persistence/PdoConnection.php:82,91` | B6 (id après INSERT) |
| Markup `access-code` + CSRF + boutons | `app/src/Views/pages/session/create.php` | F1 |

### Correction issue de la Phase 3
- ⚠️ Le nom « Nouvelle conversation » vit dans **`Layout/chat.php:82`** (`#convName`), **pas** dans `homeView.php`. → **F2 cible le layout** ; F3 (entrée join) reste dans `homeView`. La spec a été corrigée.

### Code réellement neuf à écrire
| Élément | Fichier | Lignes estimées |
|---|---|---|
| 2 interfaces Domain | B1, B2 | ~15 |
| 2 DTOs | B3, B4 | ~25 |
| Logique enrôle + find-or-create dans `JoinSessionService` | B5 | ~25 (le squelette validation existe déjà) |
| 2 `Pdo*Repository` | B6, B7 | ~60 |
| Http (join/showJoin/chat param + routes/DI) | B8-B11 | ~50 |
| Vue join + 2 retouches vues | F1-F3 | ~70 |

### Bilan
- **Réutilisé** : 9 éléments existants (helpers, JS, router, repo pattern, VO/entité, markup).
- **À écrire** : ~245 lignes neuves sur ~13 fichiers, dont l'essentiel est du câblage Clean Archi (interfaces + repos + DI), pas de la logique nouvelle (validation join + format code déjà éprouvés).

## Journal

| Phase | Date | Statut |
|---|---|---|
| Phase 0 — Doc & conventions | 2026-06-02 | Fait |
| Phase 1 — Diagnostic | 2026-06-02 | Validé |
| Phase 2 — Spec | 2026-06-02 | Validé |
| Phase 3 — Réutilisation | 2026-06-02 | Validé |
| Phase 4 — Codage | 2026-06-02 | Terminé (13 actions) |
| Phase 5 — Review + Vérification | 2026-06-02 | APPROVE + tests E2E OK |
| Phase 6 — Capitalisation | 2026-06-02 | Doc à jour (cette spec) |

## Vérification (Phase 5)

Tests E2E manuels (curl + DB) avec un étudiant + une session ACTIVE de test :

| Scénario | Résultat |
|---|---|
| `GET /sessions/join` (étudiant) | 200, `#join-code-input` présent |
| `POST /sessions/join` (code valide) | 302 → `/chat/{id}` ; `enrollments` + `conversations` (`SESSION - TST-123`) créés |
| Re-join (idempotence) | 302 → même `/chat/{id}` ; **1 seule** conversation |
| `GET /chat/{id}` | 200, topbar = `SESSION - TST-123` |
| `GET /chat/{autre}` (pas à moi) | 302 → `/chat` (ownership guard) |
| Enseignant → `/sessions/join` | **403** (requireRole student) |

Review Tech Lead (sous-agent indépendant) : **APPROVE**, 8/8 critères PASS.
