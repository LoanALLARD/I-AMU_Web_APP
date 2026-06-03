# Git Conventions

> Commit messages — applies to every commit on the repository.

## 1. Format

**Conventional commits**, imperative present tense, in English (subject and body).

```
<type>(<scope>): <imperative summary>

<optional body explaining the *why*, not just the *what*>
```

- ✅ `feat(sessions): add post-prompt support to session creation`
- ❌ `feat(sessions): ajoute le support du post-prompt` (French)
- ❌ `feat(sessions): added post-prompt support` (past tense)

## 2. Types

`feat` · `fix` · `chore` · `docs` · `refactor` · `test`

## 3. Scope

Matches the touched spec: `auth` · `sessions` · `chat` · `supervise` · `admin` · `research` · `rgpd` · `core`.

## 4. Rules

- One commit = one coherent slice (maybe several per spec).
- No co-author trailer by default.
- Body is optional but, when present, explains the rationale.
