# SPEC — Super Admin : connexion isolee + coquille du panel

> Date : 2026-06-08 | Statut : TERMINE (connexion + panel)
> Etend [`05-admin-research.md` §A.0](./05-admin-research.md) (pouvoirs du super
> admin deja decrits) en specifiant le **mecanisme de connexion**, absent
> jusqu'ici.

> ⚠️ **MàJ 2026-06-09 — ecarts vs ce plan :**
> - **Le script run-once `app/bin/superadmin_create.php` (B7) n'existe pas** :
>   le dossier `app/bin/` est absent. Seuls `SuperAdministratorRepository::count()`
>   / `::create()` le preparent ; en dev, la fixture
>   [`02_dev_fixtures.sql`](../../database/seeds/02_dev_fixtures.sql) insere
>   `admin@univ-amu.fr`. **TODO** : creer ce script.
> - **Migration repliee dans le schema** : `created_at` / `last_login_at` sont
>   directement dans [`01_schema.sql`](../../database/schema/01_schema.sql)
>   (pas de dossier `database/migrations/`).
> - **Le panel a depasse la coquille (D5)** : `SuperAdminController::index`
>   redirige vers `/super-admin/department-admins` ; la gestion des **domaines
>   email** (`/super-admin/email-domains`, CRUD via `EmailDomainService`) est
>   **fonctionnelle** ; `places` et `department-admins` restent des coquilles.
>   Il n'y a pas de vue `dashboard.php` (vues : `login.php`, `email-domains.php`,
>   `places.php`, `department-admins.php` + partials).

## 0. Statut

- **Priorite** : must-have (porte d'entree de toute l'administration plateforme).
- **Depend de** : 00-foundations, 01-auth-account (patterns de session / guards reutilises).
- **Etat actuel** : table `super_administrators` presente mais **minimale**
  ([`01_schema.sql:52`](../../database/schema/01_schema.sql)) ; aucun code de
  connexion ni de panel. Greenfield.

## 0bis. Decisions (validees avec le PO le 2026-06-08)

| # | Decision | Choix retenu |
|---|---|---|
| D1 | Acces a l'URL de connexion | **Chemin fixe non lie** : `/super-admin/login`, jamais affiche dans l'UI (aucun bouton/lien interne). La securite reelle vient de l'auth, pas du secret du chemin. |
| D2 | Durcissement securite | **Aucun pour cette iteration.** Rate limiting + verrouillage de compte sont **planifies** (cf. §8.2) — la base de donnees et le service sont concus pour les accueillir sans refonte. |
| D3 | Isolation de session | **Session separee + exclusive** : cle dediee `$_SESSION['super_admin_id']`. Se connecter en super admin **detruit** toute session utilisateur en cours, et inversement. On ne peut jamais etre les deux a la fois. |
| D4 | Perimetre de l'iteration | **Auth + coquille du panel** : connexion / deconnexion + un dashboard d'accueil protege. Les fonctionnalites metier (invitations, sites, departements, domaines email) restent dans la spec 05 et arriveront ensuite. |
| D5 | Contenu du dashboard | **Juste un titre.** Aucune statistique ni compteur pour cette iteration — coquille minimale. |
| D6 | Plafond du nombre de super admins | **Aucun cap pour le moment.** Le plafond evoque par [project.md §8](../project.md) / [05 §A.0.1](./05-admin-research.md) sera applique plus tard, dans le futur service de creation (invitation), pas dans le bootstrap. |
| D7 | Layout | **Dedie** : `layout/superadmin.php` distinct, pour la page de login comme pour le panel. |

## 1. Objectifs

Donner aux super administrateurs une **porte d'entree distincte et isolee** de
l'authentification utilisateur :
- une page de connexion dediee, accessible **uniquement par URL** (`/super-admin/login`) ;
- une session **totalement separee** de celle des `users`, **mutuellement exclusive** ;
- un **panel d'accueil protege** (coquille) reserve a cette session ;
- un **script de bootstrap run-once** pour creer le premier super admin (sans mot de passe commite).

Pourquoi : le super admin detient le pouvoir maximal et n'est volontairement
**pas** un `users` (defense en profondeur — une fuite de la table `users` ne doit
pas exposer ces comptes, cf. [05 §A.0](./05-admin-research.md)). La connexion doit
respecter cette isolation jusqu'au niveau de la session PHP.

## 2. User stories

- En tant que **super admin**, je me connecte via une URL connue de moi seul
  (`/super-admin/login`), avec un formulaire distinct de celui des utilisateurs.
- En tant que **super admin**, une fois connecte j'atterris sur un **panel**
  reserve, inaccessible aux comptes `users`.
- En tant que **super admin**, si je me connecte alors qu'une session utilisateur
  est ouverte dans le meme navigateur, **cette session est fermee** (exclusivite).
- En tant qu'**utilisateur normal**, si je tombe sur `/super-admin`, je suis
  **renvoye** vers la connexion super admin sans rien voir du panel.
- En tant qu'**exploitant**, je cree le **premier** super admin via un script qui
  **refuse de s'executer** si la table n'est pas vide, sans mot de passe en clair
  dans le depot.

## 3. Domaine

Le super admin **n'est pas** un `User` (il vit hors de `users`). On ne cree pas
d'entite riche pour cette iteration : un **repository** expose les lignes de
`super_administrators` sous forme de tableaux associatifs (meme approche que
`UserRepository`, cf. [`AuthService.php:49`](../../app/src/Services/AuthService.php)).

### Interface attendue (repository)

```php
// Models\SuperAdministratorRepository (recoit un PDO)
public function findByEmail(string $email): ?array;   // ligne complete ou null
public function findById(int $id): ?array;
public function touchLastLogin(int $id): void;         // last_login_at = NOW()
public function count(): int;                          // pour le cap + le bootstrap
```

> Pas de `UserRole` pour le super admin : ce n'est **pas** un role de `users`
> (cf. [01 §3 Value Objects](./01-auth-account.md)). Le droit d'acces se lit
> uniquement a `$_SESSION['super_admin_id']`.

## 4. Application (use-cases)

### `Services\SuperAdminAuthService` (recoit un PDO, instancie le repository)

| Methode | Comportement |
|---|---|
| `login(string $email, string $password): array` | `{success:true}` ou `{success:false, error:string}`. Cherche par email, verifie via `password_verify`. **En cas de succes** : (1) **vide entierement la session** (`$_SESSION = []`) pour casser toute identite utilisateur residuelle — c'est l'exclusivite D3 ; (2) `session_regenerate_id(true)` ; (3) ecrit les cles `super_admin_*` ; (4) `touchLastLogin()`. |
| `logout(): void` | Vide la session + supprime le cookie de session + `session_destroy()` (meme pattern que [`AuthService::logout()`](../../app/src/Services/AuthService.php:436)). |

Cles de session ecrites par `login()` (prefixe `super_admin_` pour ne **jamais**
collisionner avec les cles `user_*` de [`AuthService.php:74`](../../app/src/Services/AuthService.php)) :

```php
$_SESSION['super_admin_id']         = (int) $row['id'];
$_SESSION['super_admin_email']      = (string) $row['email'];
$_SESSION['super_admin_first_name'] = (string) $row['first_name'];
$_SESSION['super_admin_last_name']  = (string) $row['last_name'];
```

> **Exclusivite symetrique (D3) — point de contact dans l'existant** :
> [`AuthService::login()`](../../app/src/Services/AuthService.php:43) doit, lui
> aussi, repartir d'une session propre (ou au minimum `unset($_SESSION['super_admin_id'], ...)`)
> avant de poser les cles `user_*`. Sinon, se connecter en utilisateur normal
> apres un super admin laisserait les deux identites actives. Modification
> **minimale** a prevoir dans `AuthService::login()`.

### Dashboard (coquille)

**Juste un titre** (D5) : la vue affiche un titre de panel, sans statistique ni
COUNT. Pas de service ni de repository de dashboard pour cette iteration. Les
cartes/compteurs viendront avec les features de la spec 05.

## 5. Infrastructure

### Repository

`Models\SuperAdministratorRepository implements` l'interface du §3, sur la
connexion `Data\Database::getConnection()`. **Requetes preparees uniquement.**

### Script de bootstrap run-once

`app/bin/superadmin_create.php` (nouveau dossier `app/bin/`) :

1. `require __DIR__ . '/../src/bootstrap.php';` pour disposer de l'autoloader,
   de la config et de `Data\Database`.
2. Lit l'email + le mot de passe depuis l'**environnement** (`SUPERADMIN_EMAIL`,
   `SUPERADMIN_PASSWORD`) — **jamais** de valeur par defaut commitee.
3. **Refuse** si `SuperAdministratorRepository::count() > 0` (idempotence / verrou).
4. Insere avec `password_hash($password, PASSWORD_DEFAULT)`.

> Pas de plafond verifie ici (D6) : le bootstrap ne cree de toute facon **qu'un
> seul** super admin (il refuse des que la table est non vide). Le cap global
> sera applique le jour ou un autre chemin de creation existera (invitation).

Execution : `docker compose exec app php bin/superadmin_create.php`.

> En **dev**, [`02_dev_fixtures.sql:40`](../../database/seeds/02_dev_fixtures.sql)
> insere deja `admin@univ-amu.fr` : le script refusera donc (table non vide), ce
> qui est correct. Le script vise staging / prod, ou les fixtures dev ne sont pas
> chargees.

## 6. HTTP

### Routes (a ajouter dans [`public/index.php`](../../app/public/index.php))

```
GET   /super-admin/login    SuperAdminAuthController::showLogin
POST  /super-admin/login    SuperAdminAuthController::login     (CSRF)
POST  /super-admin/logout   SuperAdminAuthController::logout    (CSRF)
GET   /super-admin          SuperAdminController::index         (requireSuperAdmin)
```

- **Aucun lien interne** vers `/super-admin/login` (decision D1) : pas d'entree de
  menu, pas de bouton, pas de redirection automatique depuis l'app utilisateur.
- **Logout en POST + CSRF** (durcissement volontaire vs le `/logout` utilisateur en
  GET) car le panel manipule des donnees sensibles.

### Guards — a ajouter dans [`Core\Controller`](../../app/src/Core/Controller.php)

Memes patterns que `requireAuth` / `currentUser` existants :

```php
protected function requireSuperAdmin(): void;   // sinon redirect('/super-admin/login')
protected function currentSuperAdmin(): ?array; // identite super admin ou null
protected function isSuperAdmin(): bool;
```

`requireSuperAdmin()` teste `empty($_SESSION['super_admin_id'])`. Un `users`
connecte qui atteint `/super-admin` n'a **pas** cette cle (exclusivite) → il est
renvoye vers `/super-admin/login`.

### Controllers (nouveaux)

- `Controllers\SuperAdminAuthController` — `showLogin` (redirige vers `/super-admin`
  si deja connecte super admin), `login` (verifyCsrf → service → redirect `/super-admin`),
  `logout`. Calque de [`AuthController`](../../app/src/Controllers/AuthController.php)
  mais contre `SuperAdminAuthService`.
- `Controllers\SuperAdminController` — `index` : `requireSuperAdmin()` puis rend le
  dashboard.

### Views (nouvelles)

- `app/src/Views/layout/superadmin.php` — layout **distinct** (pas la nav de l'app
  utilisateur, identite visuelle differenciee) pour login + panel.
- `app/src/Views/pages/superadmin/login.php` — formulaire dedie (`csrf_field()`,
  `icon()`), visuellement distinct du login utilisateur.
- `app/src/Views/pages/superadmin/dashboard.php` — coquille : **un titre** (D5).

## 7. Base de donnees

La table existe ([`01_schema.sql:52`](../../database/schema/01_schema.sql)) mais il
manque les colonnes de connexion/tracabilite. Migration :

```sql
-- database/migrations/2026-06-08-superadmin-auth.sql
ALTER TABLE super_administrators
    ADD COLUMN created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ADD COLUMN last_login_at TIMESTAMPTZ;
```

> `created_at` sert la tracabilite (05 §A.0.1) ; `last_login_at` est l'equivalent
> de [`users.last_login_at`](../../app/src/Services/AuthService.php:72). Les
> colonnes de verrouillage arrivent a l'iteration suivante (§8.2) — **pas
> maintenant**.

## 8. Securite

### 8.1 Couvert par cette iteration

- **Isolation forte** : table separee + cles de session prefixees + exclusivite (D3).
- **Hash** : `password_hash` / `password_verify` (bcrypt, comme le reste de l'app).
- **CSRF** sur `login` et `logout`.
- **`session_regenerate_id(true)`** apres connexion (anti fixation).
- **Pas de secret commite** : mot de passe du bootstrap lu depuis l'environnement.
- **Bootstrap mono-compte** : le script refuse si la table est non vide (un seul
  super admin cree par cette voie). Le **plafond global** est differe (D6).

### 8.2 Iteration suivante (planifie, non implemente ici — D2)

Au minimum **rate limiting + verrouillage de compte**. Conception anticipee pour
eviter une refonte :
- Migration future : `failed_login_count SMALLINT NOT NULL DEFAULT 0`,
  `locked_until TIMESTAMPTZ` sur `super_administrators`.
- `SuperAdminAuthService::login()` : incremente le compteur sur echec, verrouille
  `locked_until` apres N echecs, refuse tant que `locked_until > NOW()`, remet a
  zero sur succes.
- Pistes ulterieures (a arbitrer plus tard, hors scope) : 2FA/TOTP, allowlist IP,
  timeout de session court + re-auth pour actions sensibles, journal d'audit
  (05 §A.0.1 « toutes les actions tracees »).

> A NE PAS faire passer pour de la securite : le chemin `/super-admin/login` est
> **fixe et non secret** (D1). Il n'est qu'« non decouvrable depuis l'UI » ; toute
> la protection repose sur l'authentification.

## 9. Tests (PHPUnit, a l'arrivee du harnais)

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Application | `SuperAdminAuthService::login` (repo mocke) | Bons identifiants → session `super_admin_id` posee, cles `user_*` absentes. Mauvais → `{success:false}`. |
| Unit Application | Exclusivite | Session avec `user_id` pre-rempli → apres `login()` super admin, plus de `user_id`. |
| Acceptance | `GET /super-admin` non connecte | Redirige vers `/super-admin/login`. |
| Acceptance | `POST /super-admin/login` valides | Redirige vers `/super-admin`, cookie de session pose. |
| Script | `superadmin_create.php` sur table non vide | Refuse, code de sortie non nul, rien insere. |

## 10. Anti-patterns specifiques

- Reutiliser `$_SESSION['user_id']` ou un faux `UserRole` pour le super admin —
  casse l'isolation, raison d'etre de la table separee.
- Lier `/super-admin/login` quelque part dans l'UI (bouton, menu, redirection).
- Mettre la SQL des COUNT du dashboard dans le controller ou la vue (→ repository).
- Committer un mot de passe super admin (seed prod, `.env` versionne, valeur par defaut).
- Considerer le chemin fixe comme une mesure de securite (D1).
- Implementer le rate limiting maintenant : c'est **explicitement** l'iteration
  suivante (D2).

---

## Plan d'actions

### Base de donnees
- [x] **DB1** — Creer `database/migrations/2026-06-08-superadmin-auth.sql` (ajout `created_at`, `last_login_at`).

### Backend
- [x] **B1** — Creer `Models\SuperAdministratorRepository` (`findByEmail`, `findById`, `touchLastLogin`, `count`, `create`).
- [x] **B2** — Creer `Services\SuperAdminAuthService` (`login` avec exclusivite + regenerate, `logout`).
- [x] **B3** — Ajouter a `Core\Controller` : `requireSuperAdmin()`, `currentSuperAdmin()`, `isSuperAdmin()`.
- [x] **B4** — Modifier `Services\AuthService::login()` : purger `super_admin_*` (exclusivite symetrique D3).
- [x] **B5** — Creer `Controllers\SuperAdminAuthController` (`showLogin`, `login`, `logout`).
- [x] **B6** — Creer `Controllers\SuperAdminController` (`index`, garde `requireSuperAdmin`).
- [x] **B7** — Creer `app/bin/superadmin_create.php` (run-once, env, refus si table non vide).

### Frontend / Vues
- [x] **F1** — Creer `Views/layout/superadmin.php` (layout dedie) + `assets/css/superadmin.css`.
- [x] **F2** — Creer `Views/pages/superadmin/login.php` (formulaire dedie + CSRF).
- [x] **F3** — Creer `Views/pages/superadmin/dashboard.php` (coquille : un titre).

### Config / Routing
- [x] **C1** — Enregistrer les 4 routes `/super-admin*` dans `public/index.php`.

## Fichiers concernes

### A creer
| Fichier | Raison | Actions |
|---|---|---|
| `database/migrations/2026-06-08-superadmin-auth.sql` | Colonnes connexion/tracabilite | DB1 |
| `app/src/Models/SuperAdministratorRepository.php` | Acces table isolee | B1 |
| `app/src/Services/SuperAdminAuthService.php` | Logique connexion isolee | B2 |
| `app/src/Controllers/SuperAdminAuthController.php` | Login / logout | B5 |
| `app/src/Controllers/SuperAdminController.php` | Panel (coquille) | B6 |
| `app/src/Views/layout/superadmin.php` | Layout distinct | F1 |
| `app/src/Views/pages/superadmin/login.php` | Page login dediee | F2 |
| `app/src/Views/pages/superadmin/dashboard.php` | Dashboard coquille | F3 |
| `app/bin/superadmin_create.php` | Bootstrap run-once | B7 |

### A modifier
| Fichier | Raison | Actions |
|---|---|---|
| `app/src/Core/Controller.php` | Guards super admin | B3 |
| `app/src/Services/AuthService.php` | Exclusivite symetrique | B4 |
| `app/public/index.php` | Routes `/super-admin*` | C1 |
| `database/schema/01_schema.sql` | Refleter les 2 colonnes (source de verite) | DB1 |

### A reutiliser (modeles existants)
| Fichier:ligne | Element | Pour |
|---|---|---|
| [`AuthService.php:43-89`](../../app/src/Services/AuthService.php) | Flow `login()` + regenerate | B2 |
| [`AuthService.php:436-452`](../../app/src/Services/AuthService.php) | Flow `logout()` | B2 |
| [`AuthController.php:28-74`](../../app/src/Controllers/AuthController.php) | Structure controller auth | B5 |
| [`Controller.php:151-230`](../../app/src/Core/Controller.php) | `requireAuth` / `currentUser` | B3 |
| `Views/pages/auth/login.php` | Markup de formulaire + CSRF | F2 |

## Impacts

- [ ] Multi-tenant : non (le super admin est plateforme entiere).
- [x] Permissions : nouvelle frontiere d'autorisation (`requireSuperAdmin`), distincte des roles `users`.
- [x] Consumers impactes : `AuthService::login()` (B4) et `Core\Controller` (B3, base de tous les controllers — ajout de methodes uniquement, non cassant).

## Reutilisation (Phase 3)

### Code reutilise (existe deja)
| Element | Fichier:ligne | Pour |
|---|---|---|
| `findByEmail` / `findById` / `touchLastLogin` (SELECT + UPDATE NOW()) | [`UserRepository.php:30,93,291`](../../app/src/Models/UserRepository.php) | Gabarit direct de `SuperAdministratorRepository` (B1) |
| Flow `login()` (verify + regenerate + cles session) | [`AuthService.php:43`](../../app/src/Services/AuthService.php) | Squelette de `SuperAdminAuthService::login` (B2) |
| Flow `logout()` (purge + cookie + destroy) | [`AuthService.php:436`](../../app/src/Services/AuthService.php) | `SuperAdminAuthService::logout` (B2) |
| `Csrf::rotate()` (rotation token sur borne login/logout) | [`Csrf.php:46`](../../app/src/Core/Csrf.php) | Appeler dans login/logout super admin (B2) |
| `Csrf::field()` / `csrf_field()` + `Csrf::verify()` | [`Csrf.php:36,57`](../../app/src/Core/Csrf.php) | Formulaire + verifyCsrf (F2, B5) |
| `requireAuth()` / `currentUser()` (pattern de garde) | [`Controller.php:151,213`](../../app/src/Core/Controller.php) | Calque des guards super admin (B3) |
| `Data\Database::getConnection(): PDO` (singleton) | [`Database.php:16`](../../app/src/Data/Database.php) | Repository + script (B1, B7) |
| `require src/bootstrap.php` (autoload PSR-4 + .env + helpers) | [`bootstrap.php`](../../app/src/bootstrap.php) | Amorce du script CLI (B7) |
| Markup `.register-card` + flash + `auth.css` | [`Views/pages/auth/login.php`](../../app/src/Views/pages/auth/login.php) | Page login dediee (F2) |
| Structure `<head>` + versionnage CSS | [`Views/layout/auth.php`](../../app/src/Views/layout/auth.php) | Layout dedie (F1) |

### Verifications consumers (avant B3 / B4)
- **B4 (modif `AuthService::login`)** : 2 appelants seulement, tous deux dans
  `AuthController` ([`:54`](../../app/src/Controllers/AuthController.php) et `:94`).
  Ajout d'une purge `super_admin_*` en tete → **non cassant**.
- **B3 (ajout dans `Core\Controller`)** : aucun `requireSuperAdmin` /
  `currentSuperAdmin` / `isSuperAdmin` / `super_admin_id` existant dans le code →
  **aucune collision**. Ajout de methodes a la classe de base = non cassant.

### Code a creer (neuf)
| Element | Fichier | Lignes estimees |
|---|---|---|
| Repository (4 methodes) | `SuperAdministratorRepository.php` | ~45 |
| Service auth (login + logout) | `SuperAdminAuthService.php` | ~60 |
| Guards | `Core\Controller` (ajout) | ~25 |
| Purge symetrique | `AuthService::login` (ajout) | ~3 |
| Controller auth | `SuperAdminAuthController.php` | ~55 |
| Controller panel | `SuperAdminController.php` | ~15 |
| Script bootstrap | `bin/superadmin_create.php` | ~45 |
| Layout dedie | `layout/superadmin.php` | ~25 |
| Vue login | `pages/superadmin/login.php` | ~50 |
| Vue dashboard (titre) | `pages/superadmin/dashboard.php` | ~12 |
| Migration | `2026-06-08-superadmin-auth.sql` | ~4 |
| Routes | `public/index.php` (ajout) | ~4 |

### Bilan
~**340 lignes neuves** sur **12 fichiers** (10 crees, 4 modifies), la majorite
calquee sur des patterns existants. Aucune dependance nouvelle, aucune API inventee.

## Questions ouvertes

Aucune — les trois points en suspens ont ete tranches (cf. 0bis, D5 / D6 / D7).

## Journal

| Phase | Date | Statut |
|---|---|---|
| Phase 0 - Doc & code existant | 2026-06-08 | Fait |
| Phase 1 - Diagnostic | 2026-06-08 | Valide |
| Phase 2 - Spec | 2026-06-08 | Valide |
| Phase 3 - Reutilisation | 2026-06-08 | Valide |
| Phase 4 - Codage | 2026-06-08 | Termine (php -l + PHPCS + PHPStan OK) |
| Phase 5 - Review + Verification | 2026-06-08 | APPROVE (Tech Lead, 8/8 PASS) |
| Phase 6 - Capitalisation | 2026-06-08 | Termine (cross-ref spec 05) |

## Verification runtime (2026-06-08, Docker up)

- **Migration appliquee** sur la base dev `IAMU_db` (colonnes `created_at` /
  `last_login_at` presentes). Sans elle : 500 `column "last_login_at" does not
  exist` sur `touchLastLogin` (cause du bug initial).
- **Flow teste (curl)** : `GET /super-admin/login` -> 200 ; `POST` valides
  (`admin@univ-amu.fr`) -> 302 `/super-admin` ; `GET /super-admin` -> 200 panel ;
  `last_login_at` mis a jour. Garde : `GET /super-admin` sans session -> 302 login.
  Mauvais mot de passe -> 200 + "Identifiants invalides", pas de redirect.
- **Durcissement** : `touchLastLogin()` deplace AVANT l'ecriture de session pour
  qu'une erreur DB ne laisse jamais de session a moitie authentifiee.

## Reste a faire (staging / prod)

- **Appliquer la migration** : la vraie base utilise `DB_USER` / `DB_NAME` du
  `.env` (en dev : `appIAMU` / `IAMU_db`, pas les defauts `iamu_user` / `iamu`) :
  `docker compose exec -T db psql -U "$DB_USER" -d "$DB_NAME" < database/migrations/2026-06-08-superadmin-auth.sql`
- **Creer le 1er super admin** (en dev la fixture `admin@univ-amu.fr` / `password` existe deja) :
  `docker compose exec -e SUPERADMIN_EMAIL=... -e SUPERADMIN_PASSWORD=... app php bin/superadmin_create.php`
