# I-AMU_Web_APP

## Project Details:
Development of a *Moodle* plugin that will be added to *Ametice*. It will take the form of a ***hypertext*** link in each course where it is available. This link will open a second tab where a *chat* interface will be accessible to discuss with ***AI models***.

This plugin will allow:
- Access to ***AI models*** that will have knowledge of a course's materials.
- Provide a structured learning environment with **rules on the models** as well as **available token limits**.
- Allow teachers to access exchanges between the model and their students so they can analyze how students use AI.

---

## Tests and Code Quality

Tools installed in `require-dev` in `app/composer.json` and automatically
executed in CI (GitHub Actions) on every `push` to any branch and on
each pull request.

> **Note on Composer**: the runtime uses a custom autoloader (`app/autoload.php`)
> loaded by `public/index.php`. Composer is only used to install quality tools
> (PHPStan, PHPCS, PHPUnit) — `vendor/autoload.php` is only used by these tools
> during static analysis and testing. The two PSR-4 mappings (custom and Composer)
> are deliberately identical to avoid drift between what the tools see and what
> runs in production.

### Installing Dev Dependencies

```bash
cd app
composer install
```

Generate key for the mail server
```bash
openssl rand -hex 32
```
Then put it in the both `.env` under the tag `APP_SECRET`. 

### Available Commands (run from app/)

| Command            | Tool                | Purpose                                                      |
|--------------------|---------------------|--------------------------------------------------------------|
| `composer lint`    | `php -l`            | Checks PHP syntax for all files in src/, public/, tests/.    |
| `composer stan`    | PHPStan (level 6)   | Static analysis: types, non-existent methods, dead code.     |
| `composer cs`      | PHP_CodeSniffer     | Verifies compliance with PSR-12 standard.                    |
| `composer cbf`     | PHP Code Beautifier | Automatically fixes PSR-12 style (auto-correctable issues).  |
| `composer test`    | PHPUnit 10          | Runs tests from tests/Unit and tests/Integration folders.    |
| `composer quality` | lint + stan + cs    | Chains the three main validations. Run before pushing.       |

### Usage Examples

Complete verification before a commit:

```bash
cd app
composer quality
```

Automatic style correction:

```bash
cd app
composer cbf
```

Run only tests:

```bash
cd app
composer test
```

### Continuous Integration (GitHub Actions)

The `.github/workflows/ci.yml` workflow executes three jobs in
parallel on each push::

- **PHP quality** : `lint`, `phpstan`, `phpcs` (informational
  during transition phase), `phpunit`.
- **Infrastructure** : validation of
  `docker-compose.yaml` syntax.
- **SQL Schema** : replays `database/schema.sql`  on a freshly
  instantiated PostgreSQL 17 to detect any SQL regressions.

PHPStan errors already present when the tool was introduced are
listed in `app/phpstan-baseline.neon` : CI only blocks on
**new** errors. After fixing errors, you can shrink this baseline:

```bash
cd app
composer stan -- --generate-baseline phpstan-baseline.neon
```

# Feature: LLM Model Integration and Management

This feature enables agnostic communication with various Artificial Intelligence models (such as Ollama, OpenAI, etc.). The architecture is based on the **Adapter Design Pattern**, allowing new models or API providers to be added directly through the database, without modifying the application core.

---

## Overview and Data

Currently, only the `llama3.2:1b` model is available and configured.

Each model's information is entirely driven by the database. Specifically, we store:
* The model name (`name`)
* The context window (`infoContextWindow`)
* The model size (`infoSizeOfModel`)
* The issuing company (`infoCompany`)
* The target API URL (`url`)
* The adapter type to use (`adapter_type`)

---

## Architecture and Execution Flow

When a chat request is made, the flow follows these steps:

1. **Routing:** The HTTP `POST` request arrives at the `LLMController`.
2. **Verification (Repository):** The controller calls `AiRepository` to verify if the requested model exists in the database and retrieves its configurations.
3. **Instantiation (Business Logic):** The controller instantiates the `ModelAi` (or `AI`) entity with its specific data.
4. **Adaptation (Adapter Pattern):** Based on the `adapter_type` column retrieved from the database, the application uses a specific class implementing the `LlmAdapterInterface`. This adapter is responsible for faithfully translating the request to the format expected by the target API (e.g., specific format for Ollama).
5. **Execution:** The formatted request is transmitted via cURL to the respective container or server, and the raw response is returned.

---

## Usage (Request Example)

Connecting to the application from the terminal.
You need a session, to do this create a `cookies.txt` file and then run the following command to log in.
```bash
curl -X POST http://localhost:8085/login
     -H "Content-Type: application/json"
     -c cookies.txt
     -d '{"email": "prenom.nom@etu.univ-amu.fr", "password": "your_password"}'
```

Test the application endpoint by sending raw JSON via a `curl` command in your terminal:

```bash
curl -X POST http://localhost:8085/chat \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "model" : "llama3.2:1b",
    "message" : "Introduce yourself",
    "conversation_id" : 1
  }'
```
The `conversation_id` field is optional; the provided ID must be associated with the logged-in user.


If you have already used the app, you may need to reload the Docker container images. To do this, follow these steps:
```bash
docker compose up --build -d
```

