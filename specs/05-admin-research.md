# Spec 05 — Admin & Research

## 0. Statut
- **Priorité** : nice-to-have
- **Dépend de** : 00 à 03 (et 04 si on veut le bouton d'export depuis la supervision)
- **État POC** : implémenté

Cette spec regroupe deux univers proches : l'**administration** de la
plateforme (réservée aux admins) et le **dashboard chercheur** (réservé
aux chercheurs).

---

## A — Administration

### A.1 Périmètre

Sous-pages accessibles via `/admin/*` pour un user avec le rôle `Admin` :
- **Dashboard** : stats globales (nb users par rôle, total conversations,
  interactions, sessions).
- **Utilisateurs** : liste paginée, recherche, attribution / retrait de
  rôles via boutons toggle (badges déjà bien stylés en POC).
- **Modèles LLM** : liste + bouton **Synchroniser avec Ollama** (réutilise
  `SyncOllamaModelsService` de la spec 03). **Pas d'ajout manuel** —
  le tag DOIT venir d'Ollama, sinon le chat plante.
- **Configuration** : lecture seule des sections clés de `config.php`
  (domaines email, durées RGPD, etc.).

### A.2 Domain / Application

| Service | Méthode |
|---|---|
| `GetAdminDashboardService` | `execute(): AdminDashboardView` (agrégats COUNT) |
| `ListUsersService` | `execute(string $search, int $page, int $perPage): UserListView` |
| `AttachRoleToUserService` | `execute(int $userId, UserRole)` |
| `DetachRoleFromUserService` | `execute(int $userId, UserRole)` |
| `ToggleLlmModelService` | `execute(int $modelId, bool $active)` |
| `SyncOllamaModelsService` | (déjà défini en spec 03, branché ici) |
| `GetConfigOverviewService` | `execute(): ConfigOverviewView` |

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
