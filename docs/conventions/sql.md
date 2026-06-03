# SQL Conventions

> PostgreSQL — applies to all `.sql` files.

## 1. General Rules
- SQL keywords → `UPPERCASE`
- Identifiers (tables, columns, constraints) → `lowercase snake_case`
- Every statement ends with `;`
- Indent with 4 spaces, one clause per line
- Write in english

## 2. Naming

**Tables** — lowercase snake_case plural nouns, no prefix (`users`, `sessions`, `teacher_sessions`)

**Columns** — lowercase snake_case
- Boolean → `is_` / `has_` / `can_` prefix (`is_active`, `has_access`)
- Timestamp → `_at` suffix (`created_at`, `sent_at`)
- Calendar date → `_date` suffix (`start_date`)

**Primary keys** — always named `id`, typed `BIGSERIAL`

**Foreign keys** — `{referenced_table_singular}_id` (`user_id`, `session_id`)

**Constraints**
- Primary key → `pk_{table}`
- Foreign key → `fk_{table}_{referenced_table}`
- Unique → `uq_{table}_{column}`
- Check → `ck_{table}_{column}`

**Indexes** — `idx_{table}_{column(s)}`

**ENUM types** — `{context}_type`, values in `UPPERCASE`
```sql
CREATE TYPE session_state_type AS ENUM ('DRAFT', 'PUBLISHED', 'ARCHIVED');
```

## 3. Table Structure

Column order:
1. `id` (PK)
2. Foreign key columns (`*_id`)