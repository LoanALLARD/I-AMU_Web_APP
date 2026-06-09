# SPEC — Header unifie (un seul layout authentifie)

> Date : 2026-06-09 | Statut : TERMINE (code + tests runtime OK)
> Harmonise la navbar : un seul header (celui de `layout/chat.php`) pour toutes
> les pages authentifiees, avec les **bons onglets selon le role**.

## 0. Statut
- **Priorite** : nice-to-have (coherence UI).
- **Etat actuel** : deux headers coexistent.

## 1. Probleme

| Layout | Header | Pages |
|---|---|---|
| **`chat.php`** | Topbar style (logo + label role + onglets pills + avatar) — **le bon** (capture) | `/chat`, `/sessions/*`, `/profile` |
| **`main.php`** | `<header><nav>` simple (`layoutMain.css`) | `/department-admin/*`, `/researcher/*`, erreurs |

`chat.php` se documente deja comme « Universal authenticated layout » et gere les
widgets chat (liste conversations) **uniquement** quand `$page === 'chat'` — il
convient donc aux autres pages. **Mais** son topbar ne connait que `teacher` /
`student` : il **manque** les onglets `department_admin` et `researcher`. C'est
pour ca que ces deux espaces sont restes sur `main.php`.

## 2. Decision

| # | Decision | Choix |
|---|---|---|
| D1 | Layout cible | **`layout/chat.php`** (le bon), reutilise partout — pas de nouveau layout. |
| D2 | Onglets par role (topbar + sidebar) | `teacher` -> Chat + Mes sessions ; `student` -> Chat ; `department_admin` -> **Administration** ; `researcher` -> **Espace chercheur**. Chat masque pour dept admin **et** chercheur (comme `main.php`). |
| D3 | Pages migrees | `DepartmentAdminController` et `ResearcherController` passent de `main` a `chat`. |
| D4 | Pages d'erreur | **Restent sur `main.php`** : une erreur peut s'afficher **non authentifie** (404/500), or `chat.php` suppose `$user`. `main.php` gere les deux etats. Exception assumee. |
| D5 | Sous-nav chercheur | `researcher/_header.php` (Mes acces / Donnees) est un **sous-menu de contenu**, pas le header global — **inchange**. |

## 3. Changements

> **Correctif CSS (post-test)** — les vues chercheur **et** admin reutilisent les
> classes `.admin-section` / `.admin-table` / `.no-message`, definies **uniquement**
> dans `department_admin.css`. `chat.php` doit donc charger `department_admin.css`
> pour `page === 'admin'` **ET** `page === 'researcher'` (sinon page chercheur sans
> style). `formAddModel.css` reste admin-only. (Theme OK : `department_admin.css`
> utilise `var(--white)` etc., que le theme sombre de `chat.php` redefinit.)

### B1 — `layout/chat.php` (le header devient multi-role)
- Calculer `$isResearcher = in_array('researcher', $roles, true);`.
- **Lien du logo** : dept admin -> `/department-admin`, chercheur -> `/researcher`, sinon `/chat`.
- **`$roleLabel`** : ajouter `department_admin` -> « administration », `researcher` -> « chercheur ».
- **Onglet Chat** : condition `!$isDeptAdmin` -> `!$isDeptAdmin && !$isResearcher`.
- **Nouveaux onglets** (dans `.topbar-tabs` **et** `.sidebar-nav`) :
  - `if ($isDeptAdmin)` -> « Administration » vers `/department-admin` (actif si `$page === 'admin'`).
  - `if ($isResearcher)` -> « Espace chercheur » vers `/researcher` (actif si `$page === 'researcher'`).
- **CSS** : charger `department_admin.css` quand `$page === 'admin'` (les vues dept-admin en dependent ; les vues chercheur utilisent `components.css` deja charge).

### B2 — `Controllers\DepartmentAdminController`
- Les `render('pages/department_admin/...')` et `render('pages/admin/formAddModel')`
  passent le **layout `chat`** + `'user' => $this->currentUser()` + `'page' => 'admin'`.

### B3 — `Controllers\ResearcherController`
- Les `render('pages/researcher/...')` passent le **layout `chat`** + `'user' => $this->currentUser()` + `'page' => 'researcher'`.

## 4. Fichiers concernes

### A modifier
| Fichier | Raison |
|---|---|
| `app/src/Views/layout/chat.php` | onglets + label + lien logo + CSS par role (B1) |
| `app/src/Controllers/DepartmentAdminController.php` | rendre via `chat` (B2) |
| `app/src/Controllers/ResearcherController.php` | rendre via `chat` (B3) |

### A reutiliser
| Element | Fichier:ligne |
|---|---|
| Pattern « render chat + page + user » | `SessionController::render` (override) + ses appels |
| Topbar + sidebar-nav role-gated | `layout/chat.php:142-222` |
| Onglets Administration / Espace chercheur (libelles + cibles) | `layout/main.php:52-57` |

### Hors scope (inchange)
- `main.php` (reste pour les erreurs), `researcher/_header.php` (sous-nav), `auth.php`, `superadmin.php`.
- `HomeController` : non route (mort) — ignore.

## 5. Verification
- En tant que **teacher** : Chat + Mes sessions (inchange).
- En tant que **department_admin** : `/department-admin` montre le **bon header** avec onglet **Administration** actif, sans onglet Chat.
- En tant que **researcher** : `/researcher` montre le **bon header** avec onglet **Espace chercheur** actif, sous-nav Mes acces/Donnees conservee.
- Aucune regression sur `/chat`, `/sessions`, `/profile`.
- Pages dept-admin gardent leur style (`department_admin.css` charge).

## 6. Anti-patterns
- Creer un enieme layout alors que `chat.php` est deja l'universel.
- Tirer le shell chat (liste conversations) sur les pages admin/chercheur : evite par le gating `$page === 'chat'` existant.
- Migrer les erreurs sur `chat.php` (casse en non-authentifie).

## Journal
| Phase | Date | Statut |
|---|---|---|
| Phase 1 - Diagnostic | 2026-06-09 | Valide |
| Phase 2 - Spec | 2026-06-09 | Valide |
| Phase 4 - Codage | 2026-06-09 | Termine (php -l OK, PHPCS clean sur le neuf) |
| Phase 5 - Verification | 2026-06-09 | Tests runtime OK (admin/chercheur/teacher) |

## Verification runtime (2026-06-09)

| Role / page | Header `app-topbar` | Onglet actif | Ancien header `navbar-brand` | CSS |
|---|---|---|---|---|
| admin.info -> `/department-admin` | oui | Administration | absent | `department_admin.css` charge |
| admin.info -> `/department-admin/addModel` | oui (200) | Administration | absent | `formAddModel.css` charge |
| chercheur1 -> `/researcher` | oui | Espace chercheur | absent | components (sous-nav OK) |
| jean.martin -> `/sessions` | oui | Mes sessions | absent | inchange (non-regression) |
