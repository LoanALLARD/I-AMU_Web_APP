# Spec 00 — Foundations

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : —
- **État** : **implémenté** (MVC + Domain, cf. [`app_architecture.md`](../design/app_architecture.md)).

> ⚠️ **Recadrage 2026-06** — La version initiale de cette spec décrivait un
> socle d'**architecture hexagonale** (container DI, `Application`, `Request`,
> `Validator`, `routes.php`, autoloader maison) qui a été **abandonné**. Le
> code réel est un **MVC + Domain** sans container ni câblage exhaustif. Ce
> document décrit désormais le socle **réellement en place** sous `app/src/Core/`,
> `app/src/Data/`, `app/src/Config/`, `app/src/Helpers/`.

## 1. Objectifs

Mettre en place le **socle technique minimal** pour que tout le reste puisse
se brancher dessus :
- charger les classes (autoloader Composer PSR-4),
- amorcer l'application (`bootstrap.php` : `.env`, timezone, helpers),
- recevoir une requête HTTP, la router (`Core\Router`), la dispatcher,
- offrir aux contrôleurs des utilitaires (`render`, `redirect`, `json`,
  `flash`, `input`/`query`, CSRF, gardes d'auth).

**Aucune logique métier dans cette couche.** `Core/` resterait intact si on
remplaçait toute la couche métier.

---

## 2. Composants en place

### 2.1 Autoloader — Composer PSR-4

**Il n'y a pas d'autoloader maison.** Le runtime charge
`app/vendor/autoload.php` (généré par Composer) depuis
[`app/src/bootstrap.php`](../../app/src/bootstrap.php). Le mapping PSR-4 est
déclaré dans [`app/composer.json`](../../app/composer.json) :

```json
"autoload": { "psr-4": {
    "Src\\": "src/", "Controllers\\": "src/Controllers/",
    "Core\\": "src/Core/", "Data\\": "src/Data/",
    "Models\\": "src/Models/", "Domain\\": "src/Domain/",
    "Services\\": "src/Services/"
}}
```

> Le namespace **est** le nom du dossier, **sans** préfixe `App\`.
> (Le commentaire de `index.php`/`composer.json` parlant d'un « hand-written
> autoloader » est obsolète — dette de commentaire à corriger.)

### 2.2 Bootstrap — `app/src/bootstrap.php`

Amorçage léger, **sans container** :
1. `require_once .../vendor/autoload.php` (autoload + dépendances Composer).
2. Charge `app/.env` via `vlucas/phpdotenv` (`safeLoad()` — un `.env`
   manquant n'échoue pas : en Docker les vars `DB_*` / `OLLAMA_URL` sont
   injectées par `docker-compose`).
3. `date_default_timezone_set(APP_TIMEZONE)` (par défaut `Europe/Paris`).
4. Charge les helpers de vue globaux : `Helpers/icons.php` (`icon()`) et
   `Helpers/csrf.php` (`csrf_field()`).

### 2.3 Front controller + routing — `app/public/index.php`

Pas de fichier `routes.php` séparé : les routes sont **déclarées en ligne**
dans `index.php`, qui :
1. `require .../src/bootstrap.php`, puis `session_start()`.
2. Instancie `new Core\Router()` et enregistre les routes
   (`$router->add('GET'|'POST', '/path', fn(...) => (new XxxController())->method(...))`).
   Les contrôleurs sont instanciés **au dispatch**.
3. Appelle `$router->compare($uri, $method)` dans un `try/catch` global qui
   transforme `HttpException` et tout `Throwable` en page d'erreur via
   `ErrorController::show($code, $e)`.

### 2.4 Router — `Core\Router`

- `add(string $method, string $path, callable $cb): void` — convertit chaque
  `{name}` en groupe regex nommé `(?P<name>[^/]+)`, ancre les deux bouts.
- `dispatch(string $url, string $method): void` — matche méthode + path,
  extrait les paramètres dans l'ordre de déclaration et les passe à la
  callback. Aucun match → `throw new HttpException(404, ...)`.
- `compare(...)` — alias `@deprecated` de `dispatch()`, encore utilisé par
  `index.php`.

### 2.5 Controller abstrait — `Core\Controller`

Base de **tous** les contrôleurs. Méthodes réellement exposées :

```php
// rendu
protected function render(string $template, array $data = [], string $layout = 'main'): void;
protected function renderPartial(string $template, array $data = []): void;
protected function capturePartial(string $template, array $data = []): string; // partial -> string
protected function json(mixed $payload, int $status = 200): never;

// IO + flux
protected function redirect(string $url): never;
protected function flash(string $type, string $message): void;        // _flash en session
protected function input(string $key, mixed $default = null): mixed;  // $_POST
protected function query(string $key, mixed $default = null): mixed;  // $_GET
protected function wantsJson(): bool;                                  // header X-Requested-With

// auth + CSRF
protected function requireAuth(): void;                 // redirect /login si pas connecté
protected function hasRole(string $role): bool;
protected function requireRole(string $role): void;     // renderForbidden() sinon
protected function requireAnyRole(array $roles): void;
protected function verifyCsrf(): void;                  // 419 + flash + redirect si KO
protected function currentUser(): ?array;
protected function renderForbidden(): never;            // 403

// super admin (identité isolée, cf. SPEC-superadmin-auth.md)
protected function requireSuperAdmin(): void;
protected function isSuperAdmin(): bool;
protected function currentSuperAdmin(): ?array;
```

> **Pas de `Request`** : on lit les entrées via `input()`/`query()` (qui
> encapsulent `$_POST`/`$_GET`). `render()` nomme son 1er paramètre
> `$template` (et non `$view`) pour ne pas écraser une clé `view` passée à
> la vue (cas du dashboard de session).

### 2.6 CSRF — `Core\Csrf` (+ helper `csrf_field()`)

- `Csrf::generateToken(): string` — génère/réutilise le jeton de session
  (32 octets hex). **Réutilisé** sur un même rendu pour que plusieurs formes
  émettent le même jeton.
- `Csrf::field(): string` — `<input type="hidden" name="_csrf_token" ...>`.
- `Csrf::rotate(): string` — régénère le jeton (login/logout).
- `Csrf::verify(?string $token = null): bool` — compare via `hash_equals` au
  jeton de session ; à défaut d'argument, lit `$_POST['_csrf_token']`.
- Helper de vue global `csrf_field()` (dans `Helpers/csrf.php`).

Tout formulaire `POST` **doit** inclure `csrf_field()` ; les contrôleurs
appellent `verifyCsrf()` (ou `Csrf::verify()` pour les endpoints JSON)
avant toute action mutante.

### 2.7 Exceptions HTTP — `Core\HttpException`

`HttpException extends RuntimeException` porte un `statusCode()`. Levée par
le routeur (404) ou n'importe où pour aborter avec un statut + message. Le
`try/catch` de `index.php` la rend via `ErrorController`.

> Il n'y a **pas** de `Validator` générique : la validation de formulaire
> vit dans des classes dédiées (`Services\CreateSessionForm`) ou dans les
> services (`AuthService::validateRegistration`, etc.).

### 2.8 Connexion BD — `Data\Database`

Singleton : `Data\Database::getConnection(): PDO` (lit `Config/config.php`,
ouvre une connexion `pgsql`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`,
`EMULATE_PREPARES=false`, et fixe la timezone de session). **Seul** point
autorisé à instancier PDO. Un Controller passe cette connexion au Service
(`new AuthService(Database::getConnection())`).

### 2.9 Config + Helpers

- `Config/config.php` — retourne un tableau (`database`, `timezone`, `mail`,
  `app.url`, `debug`), valeurs lues depuis `$_ENV` avec des défauts.
- `Helpers/icons.php` — `icon($name, $class)` (SVG Lucide inline).
- `Helpers/csrf.php` — `csrf_field()`.

---

## 3. Réutilisation du POC

Le socle est déjà en place ; le POC ne sert plus que de référence
ponctuelle (`git show poc:app/core/Csrf.php`, `git show poc:app/helpers/icons.php`).
Anti-patterns du POC à **ne pas** réintroduire : `Application::getInstance()`,
`Database::getInstance()` appelé depuis le métier, `$_POST` direct dans les
contrôleurs (passer par `input()`).

---

## 4. Structure de dossier réelle

```
app/
├── composer.json / composer.lock         ← autoload PSR-4 + dev tools
├── vendor/                               ← autoload.php (runtime) + phpdotenv
├── public/
│   ├── assets/                           ← css, js, vendor (marked/highlight/purify), img
│   └── index.php                         ← front controller : routes + dispatch + try/catch
└── src/
    ├── bootstrap.php                     ← autoload + .env + timezone + helpers
    ├── Config/config.php
    ├── Core/                             ← Router, Controller, Csrf, HttpException
    ├── Data/Database.php                 ← singleton PDO
    ├── Domain/                           ← entités + LlmAdaptaterInterface + OllamaAdaptater
    ├── Helpers/                          ← icons.php, csrf.php
    ├── Models/                           ← *Repository (accès données)
    ├── Services/                         ← logique applicative
    └── Views/                            ← layout/ + pages/ + partials/
```

---

## 5. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit | `Core\Csrf::verify` | Échoue si jeton absent/différent ; OK si égal (`CsrfTest`). |
| Unit | `Core\HttpException` | Porte le bon `statusCode()` (`HttpExceptionTest`). |
| Unit | Domain (`Session`, enums, `Ai`, `Document`…) | Invariants, transitions, labels (suite `tests/Unit/Domain`). |

> Le harnais PHPUnit existe (`tests/Unit/...`). Les tests de schéma DB
> tournent via pgTAP (`tests/db/*.sql`, profil Docker `test`).

---

## 6. Critère « spec terminée »

- [x] Autoload Composer résout 100 % des classes `Core\`/`Controllers\`/etc.
- [x] `bootstrap.php` charge `.env` (tolérant à l'absence) + helpers.
- [x] Le routeur gère les paramètres dynamiques `{id}` et le 404 global.
- [x] `Core\Controller` expose rendu / IO / flash / CSRF / gardes d'auth.
- [x] `Data\Database` est le seul point d'instanciation PDO.

---

## 7. Anti-patterns spécifiques

- ❌ Lire `$_POST` / `$_GET` directement dans un contrôleur — passer par
  `input()` / `query()`.
- ❌ Instancier PDO ailleurs que dans `Data\Database`.
- ❌ Réintroduire un container DI / un Service Locator global — on instancie
  directement (`new XxxService($pdo)`).
- ❌ Multiplier les interfaces « au cas où » — on n'en garde qu'une, l'adaptateur
  LLM (`LlmAdaptaterInterface`).
