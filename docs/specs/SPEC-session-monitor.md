# SPEC: session-monitor — Suivi des étudiants d'une session

> Date: 2026-06-02 | Branche: `dev` | Statut: **LIVRÉ**

> ⚠️ **MàJ 2026-06-09.** Le suivi est implémenté
> (`SessionController::monitor` → `SessionService::monitor` →
> `SessionRepository::monitorStudents` / `interactionsOfConversation`).
> Trois évolutions depuis ce plan :
> - **Accès élargi** : le garde n'est plus `loadOwned` mais **`loadViewable`**
>   (owner **ou** prof responsable rattaché via `teacher_resources`), avec un
>   flag **`canManage`** côté vue — cf. [SPEC-session-supervisors](./SPEC-session-supervisors.md).
> - **Sélection par conversation** : l'URL lit **`?conversation={id}`**
>   (et non `?student=`).
> - **La persistance est câblée** : `LLMController::handleChat` enregistre
>   désormais chaque interaction → la page n'est plus vide (la « Cause racine »
>   du diagnostic est résolue).
> - **(Dé)activation d'étudiant** ajoutée : `POST /sessions/{id}/student-status`
>   (visible si `canManage`).

## Contexte

Un enseignant doit pouvoir **suivre** une session **en cours (ACTIVE)** ou
**terminée (ENDED)** : retrouver tous les étudiants qui s'y sont liés
(enrôlés) et, pour chacun, consulter l'**historique de ses prompts/réponses**.
Bouton **« Suivi »** sur le dashboard → page 2 panneaux (liste à gauche,
historique de l'étudiant sélectionné à droite), à l'image de la maquette
partagée.

## Décisions (validées)

| Sujet | Décision |
|---|---|
| Persistance des interactions | **Hors périmètre** — page en **lecture seule** sur `interactions`. (⚠️ voir Diagnostic : le chat n'enregistre rien aujourd'hui → la page sera vide tant que la persistance n'est pas câblée.) |
| Ampleur | **MVP** : liste étudiants + historique prompts/réponses en lecture seule |
| Bouton | **« Suivi »**, visible pour les sessions **ACTIVE + ENDED** |

## Diagnostic (Phase 1)

| Élément | État | Source |
|---|---|---|
| `enrollments (student_id, session_id)` | ✅ qui a rejoint | DB |
| `conversations (user_id, session_id, name)` | ✅ 1 conv / étudiant / session | DB |
| `interactions (conversation_id, prompt, response, model_id, sent_at…)` | ✅ table prête, **0 ligne** | DB |
| `LLMController` | persiste l'interaction + gère `conversation_id`/`user_email` | `Controllers/LLMController.php` |
| **Composer chat** (`homeView`) | n'envoie que `{model, message, context}` → pas de `user_email`/`conversation_id` → **rien n'est enregistré** | `homeView.php:156` |

**Cause racine (pour la persistance, hors périmètre ici)** : le front n'envoie pas les champs requis. **Conséquence assumée** : la page de suivi fonctionne mais reste vide jusqu'à ce que la persistance soit câblée (autre tâche).

## Plan d'actions

### Models — `SessionRepository`
- [ ] **B1** — `monitorStudents(int $sessionId): array` : par étudiant enrôlé → `student_id`, `first_name`, `last_name`, `conversation_id`, `prompt_count`, `last_activity`, `last_model`. (JOIN enrollments→users, LEFT JOIN conversations, LEFT JOIN interactions ; GROUP BY ; ORDER BY nom.)
- [ ] **B2** — `studentTranscript(int $sessionId, int $studentId): array` : interactions de la conversation de cet étudiant pour cette session → `prompt`, `response`, `model_name`, `sent_at` (ORDER BY `sent_at`).

### Services — `SessionService`
- [ ] **B3** — `monitor(int $sessionId, ?int $studentId): array` : assemble le header session (nom, code, statut), la liste des étudiants (formatée), et le transcript de l'étudiant sélectionné si `studentId`.
- [ ] **B4** — `dashboard()` : ajouter le flag `canMonitor` (computedStatus ∈ {ACTIVE, ENDED}).

### Controllers — `SessionController`
- [ ] **B5** — `monitor(string $id)` : `requireRole('teacher')`, `loadOwned`, refuser si statut ∉ {ACTIVE, ENDED} (flash + redirect dashboard), lire `?student=` via `query()`, rendre `pages/session/monitor`.

### Vues
- [ ] **F1** — `pages/session/monitor.php` : 2 panneaux — gauche liste étudiants (lien `?student=X`, nom + nb prompts + dernière activité + modèle), droite transcript de l'étudiant (prompts/réponses) ou « sélectionnez un étudiant ».
- [ ] **F2** — `dashboard.php` : bouton **« Suivi »** dans `.dashboard-actions` si `$view['canMonitor']`.

### Routes — `index.php`
- [ ] **B6** — `GET /sessions/{id}/monitor` → `SessionController::monitor($id)`.

### CSS — `sessions.css`
- [ ] **F3** — styles 2 panneaux (`.monitor-grid`, `.monitor-list`, `.monitor-student`, `.monitor-transcript`, bulles prompt/réponse).

## Fichiers concernés

### À modifier
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Models/SessionRepository.php` | requêtes suivi | B1, B2 |
| `app/src/Services/SessionService.php` | assemblage + canMonitor | B3, B4 |
| `app/src/Controllers/SessionController.php` | action monitor | B5 |
| `app/src/Views/pages/session/dashboard.php` | bouton Suivi | F2 |
| `app/public/index.php` | route | B6 |
| `app/public/assets/css/sessions.css` | styles | F3 |

### À créer
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Views/pages/session/monitor.php` | page suivi 2 panneaux | F1 |

### À réutiliser
| Élément | Pour |
|---|---|
| Helpers `requireRole`/`query`/`render`/`redirect`/`loadOwned` | B5 |
| Patron requêtes `SessionRepository` (PDO préparé) | B1, B2 |
| Layout `chat` + `.page-header`/`.dashboard-*` + `.btn` | F1, F2 |

## Impacts
- **Permissions** : enseignant **propriétaire** uniquement (`loadOwned`). Pas d'accès étudiant.
- **Sécurité** : requêtes préparées ; on ne lit que les conversations/interactions de la session possédée.
- **Données** : aucune migration (tables déjà là). Lecture seule.
- **Limite connue** : vide tant que la persistance des interactions n'est pas câblée (hors périmètre, décidé).

## Journal
| Phase | Date | Statut |
|---|---|---|
| Phase 1 — Diagnostic | 2026-06-02 | Validé |
| Phase 2 — Spec | 2026-06-02 | En attente validation |
| Phase 3 — Codage | — | — |
| Phase 4 — Vérification | — | — |
