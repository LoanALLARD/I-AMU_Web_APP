# SPEC — Profs responsables (co-supervision via teacher_resources)

> Date : 2026-06-09 | Statut : TERMINE (code + review APPROVE + tests runtime OK)
> Etend [`02-sessions.md`](./02-sessions.md) et [`04-supervise.md`](./04-supervise.md).
> **Modele revu** : la co-supervision est portee au **niveau ressource** par la
> table existante **`teacher_resources`** (pas de table dediee, pas de migration).

## 0. Statut

- **Priorite** : nice-to-have (co-encadrement enseignant).
- **Depend de** : 02-sessions, 04-supervise (vue monitor).
- **Etat actuel** : acces session = **un seul** prof (`resources.owner_id`).
  `teacher_resources` existe au schema mais **n'est lue/ecrite nulle part** dans le
  code (table morte). On la reactive comme support des responsables.

## 0bis. Decisions

| # | Decision | Choix |
|---|---|---|
| D1 | Perimetre d'acces du responsable | **Suivi (monitor) + dashboard + export**, en **lecture seule**. |
| D2 | Stockage de la relation | **`teacher_resources`** (teacher_id, resource_id) — pas de nouvelle table. |
| D3 | Distinction des roles | **`resources.owner_id` = gerant** (tous droits) ; **`teacher_resources` (teacher_id ≠ owner_id) = responsable** (lecture seule). |
| D4 | Granularite | **Niveau ressource** : un responsable couvre **toutes les sessions** (presentes et futures) de la ressource. |
| D5 | Moderation etudiants | Un responsable **ne mute rien** : edition, start/end/cancel, (dé)activation d'etudiant restent **owner only**. |
| D6 | Libelle UI | **« Profs responsables ».** |
| D7 | Gestion de l'ajout | **Hors scope de cette iteration.** Pas de page « Mes Ressources », pas d'UI d'ajout/retrait. On livre **uniquement** le modele d'acces lecture seule + la decouverte via « Sessions surveillees ». L'ajout/retrait (ecriture `teacher_resources`) est **defere** (cf. §11). |
| D8 | Notification mail | Pas pour le moment. **TODO** prepare dans le code (defere). |

## 1. Objectifs

Faire la difference, sur une ressource, entre :
- le **proprietaire** (`owner_id`) qui pilote ses sessions,
- les **autres enseignants rattaches** (`teacher_resources`) qui peuvent
  **surveiller** les sessions de cette ressource **sans rien modifier**.

## 2. User stories

- En tant que **prof responsable** (rattache a une ressource via `teacher_resources`,
  mais pas proprietaire), je vois les **sessions** de cette ressource en
  **lecture seule** (suivi live, dashboard, export) — **aucun bouton d'action** —
  via une section « Sessions surveillees » de ma liste de sessions.
- En tant que **proprietaire**, je garde **tous** les droits sur mes sessions ;
  un responsable ne peut ni editer, ni demarrer/terminer/annuler, ni (dé)activer un etudiant.

## 3. Domaine

Pas de nouvelle entite ni table. On **reactive** `teacher_resources`.

### Modele d'acces (cle de toute la spec)

Pour une session `S` rattachee a la ressource `R` :

| Prof | Condition | Droits sur les sessions de R |
|---|---|---|
| **Proprietaire** | `R.owner_id = teacher.id` | **Gestion** : voir + editer + start/end/cancel + (dé)activer etudiant |
| **Responsable** | ligne `(R.id, teacher.id)` dans `teacher_resources` **et** `teacher.id ≠ R.owner_id` | **Lecture seule** : suivi + dashboard + export |
| **Autre** | — | aucun (403) |

### Positionnement dans le MCD

| Relation MCD | Cardinalites | Table | Sens |
|---|---|---|---|
| **ManagedBy** | RESOURCE 1,1 → TEACHER | `resources.owner_id` | proprietaire / gerant |
| **TeachesIn** | TEACHER 0,n ↔ RESOURCE 0,n | `teacher_resources` | **rattachement = responsable** (≠ owner) |
| **Accesses** | STUDENT 0,n ↔ RESOURCE 0,n | `student_resources` | acces eleve |

> On **reutilise TeachesIn** (`teacher_resources`) comme support des responsables :
> « responsable de R » = « rattache a R via TeachesIn, sans en etre l'owner ».
> Aucune patte nouvelle dans le MCD (contrairement au design precedent par session).

## 4. Application (use-cases) — `Services\SessionService`

| Methode | Comportement |
|---|---|
| `canView(Session $s, int $teacherId): bool` | `true` si `teacherId` est l'owner de la ressource **ou** rattache via `teacher_resources`. Base du garde `loadViewable`. |
| `listSupervisedForTeacher(int $teacherId): array` | Sessions des ressources ou le prof est rattache (`teacher_resources`) **sans** en etre l'owner — pour la section « Sessions surveillees ». |

## 5. Infrastructure (repositories)

Reutilisation + ajouts (SQL prepare, dans les repositories) :

- **`Models\ResourceRepository`** (existant) — ajouter :
  - `isResourceTeacher(int $resourceId, int $teacherId): bool` — ligne presente dans `teacher_resources`.
- **`Models\SessionRepository`** (existant) — ajouter :
  - `findSupervisedByTeacher(int $teacherId): array` — sessions des ressources rattachees (hors owner). Meme projection que `findAllByTeacher`.

`findSupervisedByTeacher` (forme) :
```sql
SELECT s.id, s.resource_id, r.owner_id AS teacher_id, s.name, s.type, s.status, ...
FROM sessions s
JOIN resources r          ON r.id = s.resource_id
JOIN teacher_resources tr ON tr.resource_id = r.id
WHERE tr.teacher_id = :tid
  AND r.owner_id <> :tid        -- responsable, pas proprietaire
ORDER BY s.starts_at DESC NULLS FIRST, s.id DESC;
```

## 6. HTTP

### Routes

**Aucune route nouvelle.** Les actions session existantes ne changent pas d'URL ;
seul leur **garde** evolue (cf. controller).

### Controllers

- **`Controllers\SessionController`** :
  - **Nouveau garde `loadViewable(int $id): Session`** : autorise si owner **ou**
    `canView` (rattache) ; sinon `forbidden()`.
  - `monitor()` et `dashboard()` : passent de `loadOwned` a **`loadViewable`** + flag
    **`canManage`** (= owner) a la vue.
  - `export()` : autorise owner **ou responsable** (rattache) ou researcher/department_admin.
  - **Inchanges (`loadOwned`)** : `edit/update/start/end/cancel/setStudentActive`.
  - `index()` : ajoute `supervised => listSupervisedForTeacher(currentUser.id)`.

### Vues (`app/src/Views/pages/`)

- **`session/dashboard.php`** — **gater toutes les actions mutantes** derriere
  `if ($canManage)` (un responsable voit le dashboard sans boutons).
- **`session/monitor.php`** — controles `setStudentActive` (et toute action) `if ($canManage)`.
- **`session/index.php`** — section **« Sessions surveillees »** listant `supervised`
  (badge lecture seule, lien vers suivi/dashboard).

## 7. Base de donnees

**Aucune migration.** `teacher_resources` existe deja
([`01_schema.sql`](../../database/schema/01_schema.sql)) :
```sql
CREATE TABLE teacher_resources (
    teacher_id BIGINT,
    resource_id BIGINT,
    CONSTRAINT pk_teacher_resources PRIMARY KEY (teacher_id, resource_id),
    CONSTRAINT fk_teacher_resources_teacher  FOREIGN KEY (teacher_id)  REFERENCES teachers (id)  ON DELETE CASCADE,
    CONSTRAINT fk_teacher_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE
);
```
> Pour **tester** la lecture seule tant que l'ajout n'est pas construit (D7), on
> peuple `teacher_resources` via un seed / SQL manuel.

## 8. Securite / regles

- **Lecture seule reelle** : l'acces responsable ne couvre QUE des routes GET
  (monitor/dashboard/export). Les routes POST mutantes gardent `loadOwned`. Masquer
  les boutons cote vue ne suffit pas — la garde serveur est la barriere.
- **Owner ≠ responsable** : la requete responsable exclut toujours `owner_id`
  (`r.owner_id <> :tid`) pour ne pas degrader les droits de l'owner.
- **RGPD** : un responsable voit des prompts/reponses d'etudiants. Acceptable :
  enseignants rattaches a la ressource, pour co-encadrement. (La tracabilite de
  l'ajout viendra avec la gestion deferree, §11.)

## 9. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit App | `SessionService::canView` | owner -> true ; rattache -> true ; tiers -> false. |
| Acceptance | `GET /sessions/{id}/monitor` en responsable | 200, vue sans boutons d'action. |
| Acceptance | `POST /sessions/{id}/start` en responsable | 403 (loadOwned). |
| Acceptance | `GET /resources` en teacher | 200, liste owned + rattachees avec role. |
| Integration | `SessionRepository::findSupervisedByTeacher` | Renvoie les sessions des ressources rattachees, jamais celles possedees. |

## 10. Anti-patterns

- Reintroduire une table par session : on a choisi le niveau ressource (D4).
- Se reposer sur le masquage des boutons pour la securite (garder `loadOwned` cote serveur).
- Mettre la SQL `teacher_resources` dans un controller/vue (→ repository).
- Donner des droits de gestion a un rattache : rattache = lecture seule, point.

## 11. Defere (prochaine iteration)

Hors scope de cette spec (emplacement UI a definir — page ressources, panneau
dashboard owner, ou autre) :
- **Ajout / retrait** de profs responsables (ecriture de `teacher_resources`), avec
  vivier = **profs du departement** de la ressource (revalidation serveur anti-IDOR).
- **`added_by_id` / `created_at`** : si on veut tracer l'ajout, il faudra **alterer
  `teacher_resources`** (actuellement juste (teacher_id, resource_id)) — a decider a ce
  moment-la.
- **Notification mail** (D8) : `// TODO(mail): notifier le prof rattache (cf. SPEC D8).`

---

## Plan d'actions (cette iteration)

### Backend
- [x] **B1** — `ResourceRepository` : `isResourceTeacher`.
- [x] **B2** — `SessionRepository` : `findSupervisedByTeacher`.
- [x] **B3** — `SessionService` : `canView`, `listSupervisedForTeacher`.
- [x] **B4** — `SessionController` : garde `loadViewable` ; `monitor()/dashboard()` -> `loadViewable` + `canManage` ; `export()` autorise le rattache ; `index()` passe `supervised`.

### Frontend / Vues
- [x] **F1** — `session/dashboard.php` : gating `if ($canManage)` des actions (en-tete + documents).
- [x] **F2** — `session/monitor.php` : gating `if ($canManage)` du student-status.
- [x] **F3** — `session/index.php` : section « Sessions surveillees ».

## Fichiers concernes

### A creer
Aucun.

### A modifier
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Models/ResourceRepository.php` | `isResourceTeacher` | B1 |
| `app/src/Models/SessionRepository.php` | Sessions surveillees | B2 |
| `app/src/Services/SessionService.php` | `canView`, `listSupervisedForTeacher` | B3 |
| `app/src/Controllers/SessionController.php` | Garde lecture + export + index | B4 |
| `app/src/Views/pages/session/dashboard.php` | Gating actions | F1 |
| `app/src/Views/pages/session/monitor.php` | Gating actions | F2 |
| `app/src/Views/pages/session/index.php` | Section sessions surveillees | F3 |

### A reutiliser
| Element | Fichier:ligne | Pour |
|---|---|---|
| `loadOwned` (pattern de garde) | [`SessionController.php:378`](../../app/src/Controllers/SessionController.php) | `loadViewable` (B4) |
| `findAllByTeacher` (projection session) | [`SessionRepository.php`](../../app/src/Models/SessionRepository.php) | `findSupervisedByTeacher` (B2) |
| `findAllByOwner` (ressources owned) | [`ResourceRepository.php:22`](../../app/src/Models/ResourceRepository.php) | base de `findAllForTeacher` (B1) |

## Impacts

- [x] Multi-tenant / departement : indirect — la relation reste bornee a la ressource.
- [x] Permissions : nouvelle frontiere **lecture seule** (`loadViewable` vs `loadOwned`).
- [x] Consumers : `monitor()/dashboard()/export()` changent de garde ; vues dashboard/monitor doivent gater les actions derriere `canManage`.
- [x] `teacher_resources` passe de **table morte** a table utilisee (lecture).

## Reutilisation (Phase 3)

### Reutilise tel quel (existe deja)
| Element | Fichier:ligne | Pour |
|---|---|---|
| `SessionService` cable deja `$this->sessions` **et** `$this->resources` | [`SessionService.php:26-38`](../../app/src/Services/SessionService.php) | Ajouter `canView`/`listSupervisedForTeacher` sans nouveau cablage (B3) |
| `SessionRepository::SELECT` + `findAllByTeacher` | [`SessionRepository.php:17,53`](../../app/src/Models/SessionRepository.php) | Gabarit de `findSupervisedByTeacher` (reutilise `self::SELECT`, change le WHERE) (B2) |
| `Session::resourceId()` + `teacherId()` | [`Session.php:98-99`](../../app/src/Domain/Session.php) | `canView` (owner OU rattache) (B3) |
| `loadOwned` (pattern garde) | [`SessionController.php:378`](../../app/src/Controllers/SessionController.php) | Calque `loadViewable` (B4) |
| Formulaires d'action dashboard (start/end/cancel/edit/documents) | [`dashboard.php:35-60,121-144`](../../app/src/Views/pages/session/dashboard.php) | A encapsuler dans `if ($canManage)` ; **monitor + export restent visibles** (F1) |
| Form `student-status` | [`monitor.php:156`](../../app/src/Views/pages/session/monitor.php) | A encapsuler dans `if ($canManage)` (F2) |
| Boucles liste (table + cartes) | [`session/index.php:47,110`](../../app/src/Views/pages/session/index.php) | Calque pour la section « Sessions surveillees » (sans liens edit) (F3) |

### Code a creer (neuf)
| Element | Fichier | Lignes estimees |
|---|---|---|
| `isResourceTeacher` | `ResourceRepository` (ajout) | ~7 |
| `findSupervisedByTeacher` | `SessionRepository` (ajout) | ~12 |
| `canView` + `listSupervisedForTeacher` | `SessionService` (ajout) | ~12 |
| `loadViewable` + bascule monitor/dashboard/export + index | `SessionController` (ajout/modif) | ~30 |
| Gating actions | `dashboard.php` / `monitor.php` (modif) | ~8 |
| Section « Sessions surveillees » | `session/index.php` (modif) | ~40 |

### Bilan
~**110 lignes** neuves/modifiees, **0 fichier cree**, **7 modifies**, **aucune
migration** (table `teacher_resources` reutilisee). Tout calque sur des patterns
existants ; aucune dependance ni API inventee.

## Questions ouvertes

Aucune pour cette iteration. La gestion d'ajout/retrait + mail + tracabilite sont
**explicitement deferrees** (§11).

## Journal

| Phase | Date | Statut |
|---|---|---|
| Phase 0 - Doc & code | 2026-06-09 | Fait |
| Phase 1 - Diagnostic | 2026-06-09 | Valide |
| Phase 2 - Spec (modele revu teacher_resources) | 2026-06-09 | Valide |
| Phase 3 - Reutilisation | 2026-06-09 | Valide |
| Phase 4 - Codage | 2026-06-09 | Termine (php -l + PHPStan OK ; PHPCS clean sur le neuf) |
| Phase 5 - Review + Verification | 2026-06-09 | APPROVE + tests curl (owner plein / responsable lecture seule / 403 sur mutations) |
| Phase 6 - Capitalisation | 2026-06-09 | Termine (cross-ref spec 04 ; teacher_resources reactivee) |

## Verification runtime (2026-06-09)

Scenario : paul.bernard (teacher 3) rattache via `teacher_resources` a la ressource 2
(owner = marie.dupont, teacher 2), session 4 ACTIVE.
- `GET /sessions` (paul) : section « Sessions surveillees » + session 4 visibles.
- `GET /sessions/4` + `/monitor` (paul) : 200, sans formulaires d'action ni gestion documents.
- `POST /sessions/4/start` (paul, CSRF valide) : **403** (loadOwned) ; session reste ACTIVE.
- `GET /sessions/4` (marie, owner) : formulaire `/end` present — comportement inchange.
