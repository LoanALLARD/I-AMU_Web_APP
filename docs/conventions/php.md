# PHP Conventions

> PHP 8.1+ — applies to all `.php` files.
> Project-wide writing style (language, casing, forbidden characters,
> no emojis/accents in code) is defined in [`../project.md`](../project.md);
> this file only adds the PHP-specific rules.

## 1. PHP casing

- Classes, interfaces, enums: `PascalCase` (`SessionRepositoryInterface`).
- Methods, properties, variables: `camelCase` (`findById`, `$startsAt`).
- Class constants: `UPPER_SNAKE_CASE` (`STATUS_DRAFT`).
- Comments (inline, PHPDoc, TODO) in English; UI strings and flash messages in French (`'Session créée avec succès.'`).

## 2. Naming Suffixes

- Interface → `*Interface` (`SessionRepositoryInterface`).
- PDO implementation → `Pdo*Repository` (`PdoSessionRepository`).
- Application use-case → `*Service` with a single `execute()` method (`CreateSessionService`).
- HTTP controller → `*Controller` (`SessionController`).
- Input DTO → `*Request` (`CreateSessionRequest`).
- View / output DTO → `*View` or `*ViewModel` (`SessionView`).
- Domain exception → `*Exception` (`SessionNotFoundException`).

## 3. Architecture Layers

```
Http  →  Application  →  Domain  ←  Infrastructure
                              ↑          ↑
                              └─ Core ───┘
```

- **Domain** — no `use` outside `App\Domain\*` or native PHP.
- **Infrastructure** — implements Domain interfaces only.
- **Http** — receives interfaces via constructor injection; no `new ConcreteClass()`.
- **No singletons** (`getInstance()`) anywhere in business code.

## 4. Rules

- Always type arguments and return types (PHP 8.1+).
- Inject all dependencies via constructor.
- Pass a `*ViewModel` to complex views — never a raw PDO array.
- Never use superglobals (`$_POST`, `$_GET`) directly — always go through `Request`.
- DB columns stay `snake_case` in raw SQL strings — do not camelCase them to match PHP identifiers.
- No ActiveRecord (`extends Model`) — use the repository pattern.
- No hardcoded LLM model tags in seeds — sync from the Ollama API.
- No emojis in UI — use the `icon($name)` helper (renders inline Lucide SVG).

## 5. Helpers

- `icon('name', 'css-class')` — inline Lucide SVG; see `app/Helpers/icons.php`.
- `csrf_field()` — required in every `<form method="POST">`.
- `$this->flash('success', 'msg')` — store a flash message for the next render.
