# Project Context — I-AMU

> Shared source of truth for AI agents (Claude Code, Copilot) and human
> contributors. Describes the project, stack, repo state and working
> rules. Detailed code conventions live in [`docs/conventions/`](conventions/).

## 1. What I-AMU is

A **standalone web application** giving students **supervised** access to
a local LLM (Ollama), with pedagogical traceability for teachers and
export for research (no platform-side anonymisation — see spec 06).
PHP 8.1+ / PostgreSQL 14 / Ollama / Docker. Self-hosted authentication
via university email (domains configurable in config).

## 2. Read first

- **Architecture** — [`docs/design/app_architecture.md`](design/app_architecture.md): always, before touching code. Defines the layers (Core / Domain / Application / Infrastructure / Http), dependency rules, patterns.
- **Conventions** — [`docs/conventions/php.md`](conventions/php.md), [`sql.md`](conventions/sql.md), [`git.md`](conventions/git.md): code, SQL and commit rules.
- **Specs** — [`specs/README.md`](specs/README.md) to find the right spec; [`specs/00-foundations.md`](specs/00-foundations.md) → [`05-admin-research.md`](specs/05-admin-research.md) for per-scope detail.
- **Product overview** — [`README.md`](../README.md).

## 3. Repo state

- Default branch: `main`.
- Dev branch: `dev` (nearly empty — starting point of the rewrite).
- Reference branch: `poc` (complete POC; serves as an implementation library to re-adopt cleanly).
- Structure branch: reserved for folder-layout experiments.

Inspect a POC file without checking it out:

```bash
git show poc:app/Controllers/SessionController.php
```

## 4. Stack

- Language: PHP 8.1+ (typed properties, enums, `readonly`).
- DB: PostgreSQL 14 (Docker).
- LLM: native Ollama (host), reached via `host.docker.internal`.
- Dev email: maildev (intercepts everything, port 1080).
- DB admin: Adminer (port 8081).
- Web: Apache 2.4 + mod_rewrite (Docker).
- Front: vanilla JS + marked.js (markdown) + highlight.js (code).
- Tests: PHPUnit 10 (upcoming).
- Autoloader: custom (`app/autoload.php`) — not Composer's.

## 5. Writing style (all files)

These rules apply to the whole project, every language and file type:

- Code, comments, identifiers, commit messages, and documentation are written in **English**. The only French text allowed is **end-user UI strings** (button labels, flash messages, view titles), since the interface targets AMU.
- No emojis anywhere in code or UI — render icons through the `icon($name)` helper instead.
- No accented or non-ASCII characters in code or identifiers (English only); accents are fine only inside French UI strings and inside `docs/` prose.
- No Hungarian notation; no `_` prefix to mark privates — the language's visibility keyword is enough.
- Casing: `PascalCase` for types, `camelCase` for members/variables, `UPPER_SNAKE_CASE` for constants, `snake_case` for DB identifiers.
- Per-language specifics extend these rules — see [`docs/conventions/`](conventions/).

## 6. Code conventions

Sources of truth in [`docs/conventions/`](conventions/):

- **PHP** (naming, architecture, rules) — [`php.md`](conventions/php.md).
- **SQL** — [`sql.md`](conventions/sql.md).
- **Git** (commits) — [`git.md`](conventions/git.md).

## 7. Useful commands

Docker:

```bash
docker compose up -d --build     # first run
docker compose ps                # status
docker compose logs -f app       # Apache+PHP logs
docker compose exec app bash     # shell into the container
docker compose down              # stop (keep volumes)
docker compose down -v           # stop + reset DB + maildev
```

DB:

```bash
docker compose exec db psql -U iamu_user -d iamu
# Apply a migration:
docker compose exec -T db psql -U iamu_user -d iamu < database/migrations/YYYY-MM-DD-xxx.sql
```

Lint:

```bash
docker compose exec app php -l app/Core/Application.php
```

Local URLs: app `http://localhost:8080/`, Adminer `http://localhost:8081/`
(PostgreSQL → server `db`), maildev `http://localhost:1080/`, Ollama
`http://localhost:11434/api/tags`.

## 8. Accounts & access

- Default admin: `admin` / `Admin` (created by `create_test_admin.php`).
- Recognised email domains: `@etu.univ-amu.fr` → auto `student` role;
  `@univ-amu.fr` → auto `teacher` role; anything else → no auto role,
  must be assigned by an admin.

## 9. Do / don't

Do:

- Read the matching spec **before** writing code.
- Reference the POC (`git show poc:<path>`) rather than copy-pasting blindly.
- Update the spec when you discover something during implementation.
- Follow the code conventions — see [`docs/conventions/`](conventions/).

Don't (project-specific cases; general rules live in [`conventions/php.md`](conventions/php.md)):

- Hardcode LLM model tags in seeds — the tag must come from Ollama via sync.
- Use `Database::getInstance()` or `Application::getInstance()` in business code (concrete case of "no singletons").
- Write `new OllamaLlmProvider()` in a controller — go through the interface (concrete case of constructor injection).
- Use `extends Model` ActiveRecord style — a POC pattern not to carry over.

## 10. AI agent pitfalls to avoid

Common AI failure modes. The rules already covered elsewhere (POC over
guessing, follow conventions, CSRF/`Request`) are not repeated here; these
are the gaps:

- **No invented APIs or packages.** Never call a function, method, argument, or import a package you have not verified exists in this codebase or its real dependencies. Do not add a dependency to "fix" a missing symbol — a non-existent package name is a supply-chain risk (slopsquatting).
- **Do the minimum asked.** No unrequested features, options, abstractions, or drive-by refactors. If a refactor seems worth it, propose it separately first.
- **Edit, don't overwrite.** Make targeted diffs. Never rewrite a file you have not read in full; preserve unrelated code, comments, `TODO`s, and edge cases.
- **No false confidence.** State assumptions and uncertainty explicitly. Never claim a test passes, a build succeeds, or a command ran unless it actually did and you saw the output.
- **Disagree when warranted.** If the user is technically wrong, say so with reasons instead of complying to please. Do not reverse a correct position just because it is challenged.
- **Tests assert real behaviour.** Test the observable behaviour, not the implementation. Never weaken or mock away an assertion just to make a test green.
- **No hardcoded secrets.** Read secrets from config/env (`.env`), never inline them in code, seeds, or commits.
- **No string-built SQL/commands.** Always use prepared statements and parameter binding — never concatenate user input into SQL or shell commands.
- **Hold the full task.** On multi-step work, keep earlier instructions in mind; do not redo finished work or contradict a constraint set earlier in the session.

## 11. Delegate side tasks to sub-agents

When the agent supports sub-agents (e.g. Claude Code's Task tool), delegate
**ancillary work** to them as much as possible, to keep the main thread
focused and its context clean:

- Use sub-agents for side tasks: broad code search, fan-out exploration, reading large files for a single fact, running and triaging test/lint output, drafting boilerplate.
- Keep the main thread for decisions, edits, and anything needing the full conversation context.
- Prefer one sub-agent per independent side task; run them in parallel when the tasks do not depend on each other.
- Do not delegate the core change itself, or work that needs context the sub-agent cannot be given cheaply.

## 12. DB schema in brief

Main tables (see `database/schema.sql` once the project is re-imported into `dev`):

- `"user"`, `student`, `teacher`, `researcher`, `administrator` (vertical inheritance for roles).
- `session` (with `status` enum), `authorizes` (session ↔ model).
- `conversation` (with `submitted_at`), `interaction` (with `teacher_flag`, `teacher_flag_reason`, `teacher_comment`).
- `model` (the LLMs).
- `password_reset` (tokens, 1h TTL).
- Association tables: `accesses`, `teaches_in`, `managed_by`, `is_affiliated_with`, `administers`, `belongs_to`.

## 13. Handling an uncovered case

1. If a spec covers it → update that spec.
2. If it is a new scope → create `specs/0X-name.md` from the [`specs/README.md`](specs/README.md) template.
3. If it is a cross-cutting technical decision → update [`docs/design/app_architecture.md`](design/app_architecture.md) and this file.

## 14. Agent response style

When an AI agent answers in chat (not when generating code):

- Reply **in French**.
- Concise but precise: figures, clickable file references, typed code examples.
- Always state **why** a decision was made, not just what changed.
- When unsure about a convention, **check the POC** rather than inventing.
