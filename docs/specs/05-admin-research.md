# Spec 05 — Admin & Research

## 0. Statut
- **Priorité** : nice-to-have
- **Dépend de** : 00 à 03 (et 04 si on veut le bouton d'export depuis la supervision)
- **État POC** : implémenté

Cette spec regroupe deux univers proches : l'**administration** de la
plateforme (deux niveaux d'admins, voir A.0) et le **dashboard chercheur**
(réservé aux chercheurs).

---

## A — Administration

### A.0 Deux niveaux d'administration

L'administration est **hiérarchisée sur deux niveaux**, stockés dans
**deux tables distinctes** avec des périmètres différents :

| Niveau | Table | Isolation | Périmètre |
|---|---|---|---|
| **Super administrateur** | `super_administrators` | **Table totalement séparée de `users`** — un super admin n'est PAS un `users`. Isole le pouvoir maximal en cas de faille dans la table `users`. | Plateforme entière |
| **Administrateur de département** | `department_administrators` | Vertical inheritance : `id` = `users.id`. C'est un `users` avec un rôle en plus. | Son département uniquement |

> **Pourquoi deux tables ?** Le super admin a les droits les plus
> sensibles (création de comptes admin, gestion des domaines email
> autorisés). En le sortant de `users`, une compromission de la table
> `users` (injection, fuite de hash) **n'expose pas** les comptes super
> admin. C'est une mesure de défense en profondeur, pas une commodité de
> modélisation.

#### A.0.1 Super administrateur

- **Bootstrap** : le **premier** super admin est inséré par un **script
  qui ne s'exécute qu'une seule fois** (idempotent / verrou — refuse de
  recréer si la table est non vide). Pas de seed rejouable, pas de mot de
  passe par défaut commité.
- **Création des autres comptes** : un super admin ne crée jamais un
  compte directement — il **envoie une invitation par mail**. Le
  destinataire active son compte via le lien. Cela couvre la création des
  **administrateurs de département** (cf. `department_administrators.invited_by_id`).
- **Traçabilité** : **toutes** les actions d'un super admin sont tracées
  pour pouvoir remonter la chaîne en cas d'erreur ou d'abus (qui a invité
  qui, qui a créé quel département / site / domaine).
- **Nombre limité** : le nombre de super admins est **plafonné** (borne
  dure dans le service de création) pour réduire la surface de risque.
- **Pouvoirs** :
  - créer des comptes **administrateur de département** (par invitation) ;
  - créer des **sites** (`places`) et des **départements** (`departments`) ;
  - gérer les **domaines email autorisés** par IAMU (`email_domain_configs`).

#### A.0.2 Administrateur de département

Périmètre **borné à son département**. Il peut :
- **ajouter des modèles** (au département ou aux ressources de son
  département) ;
- **gérer les comptes** des élèves et enseignants de son département ;
- **autoriser les demandes de chercheur** à accéder aux données de son
  département (`researcher_authorizations.authorized_by_id`) ;
- **habiliter un compte enseignant** (passer `teachers.is_specialised =
  TRUE`) pour qu'il puisse importer ses propres modèles d'IA personnalisés
  dans ses sessions (cf. A.0.3) ;
- **gérer les modèles autorisés en mode libre** (hors session) pour son
  département (`model_department_accesses`).

#### A.0.3 Enseignant habilité (`is_specialised`)

Un enseignant **habilité** (flag `teachers.is_specialised`, posé par un
admin de département) peut **importer des modèles dans ses ressources**.
Ensuite :
- les enseignants **de cette ressource** (table `teacher_resources`)
  peuvent créer des **sessions personnalisées** en choisissant parmi les
  modèles disponibles : ceux **de la ressource** (`models.resource_id`)
  **plus** ceux **du département** (`models.department_id` /
  `model_department_accesses`) ;
- les sessions sont rejointes via un **code d'accès** (cf. spec 02).

> **Scope d'un modèle** (cf. `ck_models_scope`) — un modèle est rattaché
> soit à un **département** (`department_id`), soit à une **ressource**
> (`resource_id`), **jamais aux deux**. Un modèle de ressource ne peut pas
> être `is_shareable` (`ck_models_shareable`).

### A.1 Périmètre des pages `/admin/*`

Sous-pages accessibles via `/admin/*`, **filtrées par niveau** (super
admin = tout ; admin département = son périmètre) :
- **Dashboard** : stats globales (nb users par rôle, total conversations,
  interactions, sessions).
- **Utilisateurs** : liste paginée, recherche, attribution / retrait de
  rôles via boutons toggle (badges déjà bien stylés en POC). L'admin de
  département ne voit que les comptes de **son** département.
- **Modèles LLM** : liste + bouton **Synchroniser avec Ollama** (réutilise
  `SyncOllamaModelsService` de la spec 03). **Pas d'ajout manuel** —
  le tag DOIT venir d'Ollama, sinon le chat plante.
- **Configuration** : lecture seule des sections clés de `config.php`
  (domaines email, durées RGPD, etc.). La gestion **écriture** des
  domaines email est réservée au super admin.

### A.2 Domain / Application

**Communs (selon périmètre du caller)** :

| Service | Méthode |
|---|---|
| `GetAdminDashboardService` | `execute(): AdminDashboardView` (agrégats COUNT) |
| `ListUsersService` | `execute(string $search, int $page, int $perPage): UserListView` |
| `AttachRoleToUserService` | `execute(int $userId, UserRole)` |
| `DetachRoleFromUserService` | `execute(int $userId, UserRole)` |
| `ToggleLlmModelService` | `execute(int $modelId, bool $active)` |
| `SyncOllamaModelsService` | (déjà défini en spec 03, branché ici) |
| `GetConfigOverviewService` | `execute(): ConfigOverviewView` |

**Super admin uniquement** :

| Service | Méthode |
|---|---|
| `InviteDepartmentAdminService` | `execute(Email $email, int $departmentId, int $superAdminId)` — envoie l'invitation par mail, trace l'action. |
| `CreatePlaceService` / `CreateDepartmentService` | `execute(...)` — création de sites / départements. |
| `ManageEmailDomainService` | `execute(...)` — CRUD des `email_domain_configs`. |

**Admin de département uniquement** (borné à son département) :

| Service | Méthode |
|---|---|
| `SetTeacherSpecialisedService` | `execute(int $teacherId, bool $specialised)` — habilite / déshabilite un enseignant (cf. §A.0.3). |
| `AuthorizeResearcherService` | `execute(int $researcherId, int $departmentId, int $adminId)` — autorise un chercheur sur le département. |
| `ToggleDepartmentModelAccessService` | `execute(int $modelId, int $departmentId, bool $allowed)` — gère les modèles autorisés en mode libre. |

### A.3 HTTP — Routes

```
GET   /admin                      AdminController::index
GET   /admin/users                AdminController::users
POST  /admin/users/role           AdminController::updateRole
GET   /admin/models               AdminController::models
POST  /admin/models/toggle        AdminController::toggleModel
POST  /admin/models/sync          AdminController::syncModels
GET   /admin/config               AdminController::config
```

### A.4 Vues à reprendre du POC

| Fichier POC | Action |
|---|---|
| `app/views/pages/admin/index.php` | OK, juste rebrancher `$stats`. |
| `app/views/pages/admin/users.php` | OK ; **bien préserver** le wrapper `.roles-cell-wrap` + `.cell-top` corrigés. |
| `app/views/pages/admin/models.php` | OK ; **bien supprimer** la section "Ajouter un modèle" (retirée en POC, garder seulement le bouton Sync). |
| `app/views/pages/admin/config.php` | OK. |

---

## B — Dashboard chercheur

### B.1 Périmètre

URL : `GET /export` (peut être renommée `/research/corpus` plus tard).
Réservée au rôle `Researcher`.

Le dashboard affiche, à partir des interactions filtrées :
- **stats globales** (cours, étudiants, prompts, bytes),
- **filtres** : période (date), cours (multi), modèle, rôle utilisateur,
  longueur min, anonymisé (oui/non),
- **4 graphiques** générés en SVG/HTML pur (pas de JS chart) :
  - bar chart horizontal : volume par cours (top 12),
  - line chart : volume par semaine,
  - histogram : volume par heure de la journée,
  - histogram bucketed : longueur moyenne des prompts (`0+`, `20+`, …, `1000+` tokens),
- **sujets émergents** : nuage de mots-clés (placeholder simple basé sur
  fréquence + stopwords FR/EN),
- **export JSON** des prompts filtrés.

### B.2 Domain / Application

```php
final class ResearchFilters {
    public ?DateTimeImmutable $from;
    public ?DateTimeImmutable $to;
    /** @var list<int> */ public array $courseIds;
    public ?int $modelId;
    public ?UserRole $role;
    public int $minPromptLength;
    public bool $anonymized;
}

final class ResearchCorpusView {
    public int $totalPrompts;
    public int $filteredPrompts;
    public int $courseCount;
    public int $studentCount;
    public int $bytesTotal;
    /** @var list<array{code:string,name:string,n:int}> */     public array $byCourse;
    /** @var list<array{weekStart:string,n:int}> */            public array $byWeek;
    /** @var list<array{hour:int,n:int}> */                    public array $byHour;
    /** @var list<array{label:string,pct:int,peak:bool}> */    public array $byLength;
    /** @var list<array{word:string,n:int}> */                 public array $topics;
}
```

| Service | Méthode |
|---|---|
| `BuildResearchCorpusService` | `execute(ResearchFilters): ResearchCorpusView` |
| `ExportResearchCorpusService` | `execute(ResearchFilters): string` (JSON) |

### B.3 Infrastructure

- `PdoResearchRepository` — concentre **toutes** les agrégations SQL en
  une seule classe (sinon dispersé dans plusieurs repos artificiellement).
- L'extraction de mots-clés "sujets émergents" reste un placeholder en
  PHP (regex + stopwords). À remplacer par un vrai LDA quand le projet
  l'exigera (créer une `TopicExtractorInterface` à ce moment-là).

### B.4 HTTP — Routes

```
GET   /export                     ResearchController::index
GET   /export/json                ResearchController::exportJson
```

### B.5 Vue à reprendre du POC

`app/views/pages/dashboard/export.php` — déjà refondu en POC avec tous
les charts SVG inline. À reprendre **tel quel**, en passant juste un
`ResearchCorpusView` au lieu d'arrays.

### B.6 Anti-patterns

- ❌ Mélanger les filtres dans la query string et dans un POST. **Tout
  en GET** (URL partageable, "enregistrer la vue" = localStorage de la
  query string).
- ❌ Faire les agrégations en PHP (sortir 50 000 lignes pour les
  compter). Tout en SQL via window functions / GROUP BY.
- ❌ Régénérer le LDA placeholder à chaque page. Si la vue devient
  lente, mettre un cache fichier court (5 min) sur la query string.

---

## C — Tests communs

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Application | `AttachRoleToUserService` | Empêche d'ajouter deux fois le même rôle. |
| Unit Application | `BuildResearchCorpusService` | Mocke le repo, vérifie le formatage des `byLength` (pourcentages somment à ~100). |
| Integration | `PdoResearchRepository::byCourse` | Avec un seed de test : retourne les top N cours triés. |
| Acceptance | `GET /admin/users?search=foo` connecté admin | HTML contient les bons users. |
| Acceptance | `GET /export/json?from=...` connecté chercheur | Content-Type `application/json`, JSON parseable. |

---

## D — Réutilisation POC : récap fichiers

| Fichier POC | Cible dev |
|---|---|
| `app/controllers/AdminController.php` | Récrire en délégant aux services. `storeModel` est supprimé (déjà fait en POC). |
| `app/controllers/ExportController.php` | Devient `ResearchController`. La logique d'agrégation déménage dans `PdoResearchRepository`. |
| `app/views/pages/admin/*.php` | Réutilisables tels quels. |
| `app/views/pages/dashboard/export.php` | Idem. |
