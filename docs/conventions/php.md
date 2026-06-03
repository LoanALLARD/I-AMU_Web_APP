# PHP Conventions

> PHP 8.1+ — applies to all `.php` files.
> Project-wide writing style (language, casing, forbidden characters,
> no emojis/accents in code) is defined in [`../project.md`](../project.md);
> this file only adds the PHP-specific rules.

## 1. PHP casing

- Classes, interfaces, enums: `PascalCase` (`AiRepository`, `LlmAdaptaterInterface`).
- Methods, properties, variables: `camelCase` (`getModelByName`, `$startsAt`).
- Class constants: `UPPER_SNAKE_CASE` (`STATUS_DRAFT`).
- Comments (inline, PHPDoc, TODO) in English; UI strings and flash messages in French (`'Session créée avec succès.'`).

## 2. Naming Suffixes

- HTTP controller → `*Controller` (`AuthController`).
- Data access (Model) → `*Repository` (`AiRepository`, `UserRepository`).
- Application service → `*Service` (`AuthService`).
- LLM contract → `*Interface` (`LlmAdaptaterInterface`) — the only interface kept.
- LLM implementation → `*Adaptater` (`OllamaAdaptater`).
- Domain exception → `*Exception` (`TestException`).

## 3. Architecture Layers

MVC + Domain. The namespace is the folder name, with no `App\` prefix
(`Controllers\`, `Services\`, `Models\`, `Domain\`, `Data\`, `Core\`).

```
Controllers  →  Services  →  Models  →  Data (PDO)
      │              │           │
      └──→ Views     └──→ Domain ←┘
```

- **Controllers** — read HTTP input, call a Service/Model, render a View. No SQL.
- **Services** — application logic; use `Data\Database::getConnection()` or a Model.
- **Models** — `*Repository` classes; receive a `PDO`, hold the SQL.
- **Domain** — entities + the single `LlmAdaptaterInterface`; depends on native PHP only.
- **Data** — `Database` singleton: the only class that instantiates PDO.

## 4. Rules

- Always type arguments and return types (PHP 8.1+).
- Direct instantiation is fine (`new AuthService($pdo)`) — no DI container, no manual wiring graph. A Service that needs the DB takes a `PDO` in its constructor; the Controller passes `Data\Database::getConnection()`.
- DB access goes through `Data\Database::getConnection()` (singleton) — do not instantiate PDO elsewhere.
- All SQL lives in a Model (`*Repository`) or a Service — never in a Controller or a View.
- Always use prepared statements — never concatenate user input into SQL.
- Keep one interface only: `LlmAdaptaterInterface`. Reach the LLM through it, never `new OllamaAdaptater()` inside `Ai`.
- No ActiveRecord (`$entity->save()`) — reading and writing is the repository's job.
- Read request input via the controller's `input()` helper, not `$_GET`/`$_POST` directly.
- DB columns stay `snake_case` in raw SQL strings — do not camelCase them to match PHP identifiers.
- No hardcoded LLM model tags in seeds — sync from the Ollama API.
- No emojis in UI — use the `icon($name)` helper (renders inline Lucide SVG).

## 5. Helpers

- `icon('name', 'css-class')` — inline Lucide SVG; see `src/Helpers/`.
- `csrf_field()` — required in every `<form method="POST">`.
- `$this->flash('success', 'msg')` — store a flash message for the next render.
