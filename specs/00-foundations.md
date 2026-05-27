# Spec 00 — Foundations

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : —
- **État POC** : implémenté (à refactorer pour DI explicite)

## 1. Objectifs

Mettre en place le **socle technique minimal** pour que tout le reste
puisse se brancher dessus :
- charger les classes (autoloader natif),
- assembler les dépendances (`bootstrap.php`),
- recevoir une requête HTTP, la router, la dispatcher,
- offrir aux contrôleurs des utilitaires (render, redirect, json, flash,
  csrf, validator).

**Aucune logique métier dans cette spec.** Le but est qu'à la fin, on
puisse écrire `GET /healthz → JSON {ok:true}` en 30 lignes de code, sans
rien de plus.

---

## 2. Composants à livrer

### 2.1 Autoloader maison

Fichier : `app/autoload.php`.

- Utilise `spl_autoload_register`.
- Gère deux préfixes :
  - `App\` → `app/`
  - `PHPMailer\PHPMailer\` → `vendor/phpmailer/phpmailer/src/`
- Tente d'abord le chemin **strict PSR-4** (PascalCase), puis un
  fallback **lowercase** sur les segments intermédiaires (utile pour la
  transition depuis le POC).
- Aucune dépendance — fonctions PHP natives uniquement.

> Le POC contient déjà une version aboutie : `git show poc:app/autoload.php`.

### 2.2 Bootstrap

Fichier : `app/bootstrap.php`.

Responsabilités :
1. Charger la config (`app/config/config.php`).
2. Charger les helpers globaux (`app/Helpers/icons.php`).
3. Instancier les briques bas-niveau (PdoConnection, SystemClock).
4. Instancier les repositories (Infrastructure).
5. Instancier les services applicatifs.
6. Instancier les controllers (un par classe, partagés).
7. Retourner un tableau ou un objet `Container` (lookup par classe).

```php
return new Container([
    SessionController::class => new SessionController($startSession, $sessionRepo, …),
    ChatController::class    => new ChatController($sendPrompt, …),
    // …
]);
```

> Si le bootstrap dépasse ~200 lignes, on l'éclate en mini-factories par
> couche (`bootstrap/persistence.php`, `bootstrap/services.php`, etc.).
> Sinon, on garde un fichier unique.

### 2.3 Container minimal

`App\Core\Container` — un wrapper trivial autour d'un tableau pour :
- `get(string $key): object` — lance si manquant.
- `has(string $key): bool`.

Pas d'auto-wiring, pas de cycle de vie complexe : c'est juste un
lookup.

### 2.4 Application

`App\Core\Application` — orchestrateur principal.

```php
final class Application
{
    public function __construct(
        private Container $container,
        private array $config,
    ) {}

    public function run(): void
    {
        $this->startSession();
        $router = new Router($this->container);
        require __DIR__ . '/../config/routes.php';   // peuple $router
        $router->dispatch(Request::fromGlobals());
    }
}
```

**Changement par rapport au POC** : plus de `getInstance()` statique.
L'Application reçoit son container et sa config par injection. Cela
permet d'instancier plusieurs applications en parallèle (utile pour les
tests acceptance).

### 2.5 Router

`App\Core\Router` — routeur à table en mémoire.

- `get(path, controllerClass, method)`, `post(...)`.
- `dispatch(Request)` matche l'URL + méthode HTTP, extrait les params
  dynamiques (`{id}`), appelle `Container::get(controllerClass)`, puis
  invoque la méthode avec `Request` en premier arg + params extraits.
- 404 → `ErrorController::notFound`.
- 500 → `ErrorController::serverError` (catch global).

### 2.6 Request

`App\Core\Request` — wrapper immuable des superglobales.

```php
final class Request
{
    public static function fromGlobals(): self;

    public function method(): string;             // GET, POST, …
    public function path(): string;               // /sessions/12
    public function query(string $key, mixed $default = null): mixed;
    public function input(string $key, mixed $default = null): mixed;
    public function all(): array;                 // POST + GET
    public function isJson(): bool;
    public function jsonBody(): array;            // décode si content-type json
    public function header(string $name): ?string;
}
```

Le but est de **ne plus lire `$_POST`/`$_GET` directement** dans le
métier — uniquement via cet objet.

### 2.7 Controller abstrait

`App\Core\Controller` — base abstraite.

```php
abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void;
    protected function redirect(string $url): void;
    protected function json(mixed $payload, int $status = 200): void;
    protected function flash(string $type, string $message): void;

    protected function requireAuth(): void;            // redirect /login si pas connecté
    protected function requireRole(string $role): void;
    protected function requireAnyRole(array $roles): void;
    protected function currentUser(): array;
}
```

> Le helper `requireAuth` lit `$_SESSION`. On accepte cette dérogation à
> la "pas de superglobale en dehors de Request" — la session PHP reste
> un état serveur géré par Core.

### 2.8 CSRF

`App\Core\Csrf` — protection CSRF par jeton de session.

- `Csrf::token(): string` — génère/réutilise le jeton de la session.
- `Csrf::verify(Request $req): void` — lance `CsrfException` si manquant
  ou invalide.
- Helper de vue `csrf_field()` qui rend `<input type="hidden" name="_csrf" value="…">`.

Tous les formulaires POST **doivent** inclure ce champ. Les controllers
**doivent** appeler `$this->csrf->verify($request)` avant toute action.

### 2.9 Validator

`App\Core\Validator` — validation déclarative simple.

```php
$errors = (new Validator($request->all()))
    ->required('name')
    ->minLength('name', 3)
    ->in('type', ['TP', 'EXAM', 'SANDBOX'])
    ->date('starts_at')
    ->errors();

if ($errors) {
    $this->flash('error', implode(' · ', $errors));
    return $this->redirect('/sessions/create');
}
```

Pas une lib complète à la Laravel ; juste un fluent builder qui couvre
les cas du projet (required, min/max, length, in, email, date, int).

---

## 3. Réutilisation du POC

| Fichier POC                  | Action                                                   |
|------------------------------|----------------------------------------------------------|
| `app/autoload.php`           | **Copier tel quel** dans dev.                            |
| `app/core/Application.php`   | Garder l'idée, **retirer le singleton** `getInstance`.   |
| `app/core/Router.php`        | Garder la logique de matching.                           |
| `app/core/Controller.php`    | Garder `render`, `redirect`, `flash`. Renommer `input`/`query` en utilisant `Request`. |
| `app/core/Csrf.php`          | Réutiliser, ajouter le helper de vue `csrf_field()`.     |
| `app/core/Database.php`      | **Déplacer en** `Infrastructure/Persistence/PdoConnection.php`. Retirer le singleton. |

> ⚠️ À NE PAS copier : le `Application::getInstance()` partout, les
> appels `Database::getInstance()` depuis le métier, le `$_POST` direct
> dans les controllers.

---

## 4. Structure de dossier à la fin de la spec

```
app/
├── autoload.php
├── bootstrap.php
├── config/
│   ├── config.php
│   └── routes.php
├── Core/
│   ├── Application.php
│   ├── Container.php
│   ├── Router.php
│   ├── Request.php
│   ├── Controller.php
│   ├── Csrf.php
│   └── Validator.php
└── Helpers/
    └── icons.php
```

---

## 5. Tests à écrire

| Niveau | Cible | Exemple |
|---|---|---|
| Unit | `Request::input` | retourne la valeur POST, sinon default |
| Unit | `Router::dispatch` | matche `/sessions/{id}` et extrait `id` |
| Unit | `Validator::required` | erreurs si vide, OK si présent |
| Unit | `Csrf::verify` | lance si token absent ou différent |
| Integration | `Application::run` avec un container mocké | une route renvoie 200 |

---

## 6. Critère "spec terminée"

- [ ] `GET /healthz` répond `{ok: true}` en JSON.
- [ ] `POST /healthz/echo` avec body JSON renvoie ce qu'on a envoyé,
      sous réserve que le jeton CSRF soit fourni.
- [ ] Aucun appel à `Database::getInstance()` ou aux superglobales dans
      `app/Core/*` (sauf `Request::fromGlobals` et `Csrf`).
- [ ] Lint PHP propre, autoloader résout 100% des classes Core.

---

## 7. Anti-patterns spécifiques

- ❌ Service Locator global (`Application::getInstance()->get('foo')`).
- ❌ `new XxxService()` dans un controller — ça passe **toujours** par
  le constructeur et le container.
- ❌ Mélange superglobale + Request — pick one, ce sera Request.
- ❌ Container qui résout magiquement par réflexion. On câble à la main.
