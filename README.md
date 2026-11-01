# Issue Intake and Smart Summary System

A small Laravel 13 API for submitting support issues, commenting on them, and getting an automatically generated summary and suggested next action for each issue. Summaries are produced by a background job, never inside the request. The generator is either a rules-based engine (default, no setup) or an LLM on Groq with automatic fallback to the rules engine.

## Requirements

Docker with Docker Compose. Nothing else needs to be installed on the host.

## Setup

Important: **Docker Desktop** must be running.

```bash
cp .env.example .env
touch database/database.sqlite
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
```

`--seed` creates 7 issues across all priorities, categories and statuses, with comments, and generates their summaries immediately.

## Run

```bash
docker compose up
```

This starts two containers from the same image:

| Service | Command | Purpose |
|---|---|---|
| `app` | `php artisan serve` | The API on http://localhost:8000 |
| `queue` | `php artisan queue:work` | The worker that generates summaries |

Without the `queue` container running, new issues stay at `summary_status: pending`. After changing PHP code used by the worker, or changing `.env`, restart it (`docker compose restart queue`), because `queue:work` keeps the old code and configuration in memory.

## Choosing the summary generator

The generator is selected in `.env`:

| `SUMMARY_DRIVER` | Behaviour |
|---|---|
| `rules` (default) | Deterministic keyword and category rules. No API key, no network. |
| `groq` | Calls the Groq API. If the key is missing or the call fails, the rules engine produces the summary instead. |

To use Groq, set these in `.env` (the key is never committed), then restart the worker:

```
SUMMARY_DRIVER=groq
GROQ_API_KEY=your-key-here
GROQ_MODEL=openai/gpt-oss-20b
```

The app runs fully with no key at all, which is how it behaves out of the box.

## Tests

```bash
docker compose run --rm app php artisan test
```

Tests use an in-memory SQLite database and the `sync` queue driver, so they need no worker. `tests/TestCase.php` forces `SUMMARY_DRIVER=rules` and all Groq tests use `Http::fake()`, so the suite never calls a real API even if your `.env` has a key.

## API

All endpoints return JSON. Send `Content-Type: application/json` on requests with a body.

| Action | Request |
|---|---|
| Create issue | `POST /issues` |
| List issues | `GET /issues?status=open&priority=high&category=billing` |
| View issue with comments | `GET /issues/{id}` |
| Update issue | `PATCH /issues/{id}` |
| Add comment | `POST /issues/{id}/comments` |

Allowed values: `priority` is `low`, `medium` or `high`. `status` is `open`, `in_progress` or `resolved`. `category` is `billing`, `technical`, `account` or `general`.

```bash
# Create (responds 201 immediately with summary_status "pending")
curl -X POST http://localhost:8000/issues \
  -H "Content-Type: application/json" \
  -d '{"title":"Checkout is down","description":"Customers cannot pay. Every attempt fails.","priority":"high","category":"technical"}'

# List with combined filters; page and per_page (max 100) are also supported
curl "http://localhost:8000/issues?status=open&priority=high&category=billing"

# View one issue with its comments
curl http://localhost:8000/issues/1

# Update (changing description regenerates the summary; changing status does not)
curl -X PATCH http://localhost:8000/issues/1 \
  -H "Content-Type: application/json" \
  -d '{"status":"in_progress"}'

# Add a comment
curl -X POST http://localhost:8000/issues/1/comments \
  -H "Content-Type: application/json" \
  -d '{"author_name":"Dana","body":"Looking into it."}'
```

Errors always have the same shape:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": { "title": ["The title field is required."] }
  }
}
```

Codes in use: `validation_failed` (422), `not_found` (404), `method_not_allowed` (405), `server_error` (500).

## Architecture & Decisions

**Database: SQLite.** It needs no extra service, so the project runs on a clean machine with one command, and the data model is small. The trade-off is limited write concurrency, which would matter in production; the schema uses only portable Laravel features, so switching to MySQL or PostgreSQL is a `.env` change.

**Queue: database driver.** The `jobs` and `failed_jobs` tables already exist in Laravel, so no Redis is needed. Jobs retry 3 times with backoff (10s, then 60s). If every attempt fails, the job's `failed()` handler sets `summary_status` to `failed` and Laravel records it in `failed_jobs`. A failing generator never affects the API response.

**Async boundary.** The request saves the issue and dispatches `GenerateIssueSummary`. Creating an issue, or changing its `description`, sets `summary_status` to `pending` and dispatches the job. Changing any other field does not. The previous summary stays visible while a new one is pending. The job reads the issue when it runs, so it always summarises the latest description.

**Generator design (Path A).** `App\Services\Summary\SummaryGenerator` is the interface; the job and controllers depend only on it. There are three implementations:

- `RulesBasedSummaryGenerator`: deterministic. The summary is built from priority, category, title and the first sentence of the description; the next action comes from the first matching keyword rule, falling back to a per-category default.
- `GroqSummaryGenerator`: one HTTP call to Groq's `/responses` endpoint using Laravel's `Http` client, with no SDK. It reads the answer from the `message` item (skipping any `reasoning` items), extracts the JSON object from the text, validates that both fields are present and non-empty, and throws `SummaryGenerationException` for anything unusable: missing key, timeout, non-2xx status, or malformed output. The API key is sent as a bearer token in a header, never in the URL.
- `FallbackSummaryGenerator`: wraps a primary and a fallback generator. When the primary throws `SummaryGenerationException`, it logs a warning and returns the rules-based result. The fallback is real code that runs on every LLM failure, not a stub, and the tests cover each failure mode.

`AppServiceProvider` picks the implementation from `config('summary.driver')`. Adding another provider means writing one class that implements the interface and adding one line to that `match`.

**Prompt template.** The prompt is committed at `resources/prompts/issue-summary.md`. Issue text is placed inside `<issue>` tags and the prompt tells the model to treat it as data, which reduces prompt-injection risk from customer-written text. Placeholders are filled in a single pass (`strtr`), so text typed into an issue cannot inject other placeholders.

**Provider choice.** Groq was chosen for its free tier (no card required) and fast inference, which suits a short background job. The default model, `openai/gpt-oss-20b`, is configurable through `GROQ_MODEL`. The prompt asks for JSON and the parser validates the result instead of relying on a provider-specific JSON mode, so the same prompt and parsing would carry over to another provider.

**Data sent to a third party.** With `SUMMARY_DRIVER=groq`, issue titles and descriptions are sent to Groq. Check the provider's data terms (especially for free-tier keys) before enabling it with real customer data. The default `rules` driver sends nothing anywhere.

**`needs_attention`.** True when priority is `high` and status is not `resolved`. It is recomputed on every save of an issue (a model `saving` hook), so it cannot drift from priority or status. Resolved high-priority issues are deliberately not flagged. There is no time-based "overdue" logic.

**Queries.** The list uses `withCount('comments')` and the single view uses one eager load, so each takes a fixed number of queries. Regression tests assert the query counts.

**Validation.** Form Requests validate input. Laravel's `TrimStrings` and `ConvertEmptyStringsToNull` middleware run first, so whitespace-only values count as missing. Priority, status and category are PHP enums stored as strings. `PATCH` is a partial update, and fields that are sent still cannot be empty. Invalid filter values on the list endpoint return 422 instead of being ignored.

**Not included.** Authentication, soft deletes and an audit log are out of scope. Without auth, anyone who can reach the API can modify any issue, so it is not safe to expose publicly as it is.

### AI Usage

**Claude (Anthropic)** Used to draft the initial system architecture, Docker setup, database migrations, models, controllers, and tests, as well as to debug issues throughout development.

**Groq Cloud** Used for the LLM integration to generate automated issue summaries and suggested next actions.
