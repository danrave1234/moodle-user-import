# Moodle User Import

A small production-style application for previewing and importing users from CSV. It provides a clear React workflow for non-technical users and a Symfony Console CLI for administrators. Both adapters share the same typed PHP application service, so parsing, normalization, validation, duplicate handling, and database writes behave consistently.

The implementation deliberately stays within the coding challenge's controlled scope: one CSV workflow, one PostgreSQL table, two HTTP endpoints, and one CLI command. Preview rows are read-only; features such as editable previews are documented under [Future improvements](#future-improvements), not added to the core submission.

## What it does

- Accepts `name`, `surname`, and `email` CSV columns in any order; extra columns are ignored.
- Trims values, title-cases names with multibyte-safe functions, and lowercases email addresses.
- Reports required-field, invalid-email, in-file duplicate, and database duplicate errors by CSV row.
- Previews valid and invalid users before importing.
- Reprocesses the original file during import instead of trusting browser preview data.
- Uses a transaction and PostgreSQL `UNIQUE` constraint with conflict-safe inserts.
- Supports browser and CLI dry-run workflows with clear loading and error states.

## Architecture

```text
React
  |
  v
Slim API ----+
             |
CLI ---------+
             v
     UserImportService
             |
       +-----+----------+
       |     |          |
       v     v          v
      CSV  Validator  Repository
                         |
                         v
                     PostgreSQL
```

`UserImportService` orchestrates a streamed `CsvReader`, `UserNormalizer`, `UserValidator`, and `UserRepository`. Slim actions and the Symfony command only validate transport input, call the service, and format results. SQL stays inside `PdoUserRepository`.

## Requirements

- PHP 8.3 or newer with `mbstring`, `PDO`, and `pdo_pgsql`
- Composer 2
- Node.js 24 LTS and npm
- Docker Desktop or Docker Engine with Compose (for local PostgreSQL)

The committed lock files pin exact PHP and JavaScript dependencies.

## Setup

From the repository root:

```bash
docker compose up -d
cp .env.example .env

cd backend
composer install
php user_upload.php --create-table

cd ../frontend
npm ci
```

PowerShell equivalent for the environment file:

```powershell
Copy-Item .env.example .env
```

The Compose initialization creates separate `user_import` and `user_import_test` databases. The normal development database is never used by integration tests.

### Environment configuration

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=user_import
DB_USER=user_import
DB_PASSWORD=local_dev_password
```

Copy `.env.example`; do not commit `.env`. Database settings are validated before a connection is attempted.

## Run the application

Start the PHP API in one terminal:

```bash
cd backend
php -S localhost:8080 -t public public/index.php
```

Start Vite in another:

```bash
cd frontend
npm run dev
```

Open `http://localhost:5173`. Vite proxies `/api` requests to `http://localhost:8080`, so local CORS configuration is unnecessary.

### Web workflow

1. Choose or drop a `.csv` file.
2. Select **Preview users**.
3. Review summary counts and row-level messages.
4. Select **Import X users**. The button stays disabled until preview succeeds and at least one row is valid.
5. Review the final imported and not-imported counts. Expand **View imported users** or **View rejected rows** to verify the exact normalized records and rejection reasons from this operation.

The UI keeps the original browser `File` object and uploads it again for import. It never sends normalized preview rows as trusted input.

## CLI

Run commands from `backend`:

```bash
php user_upload.php --help
php user_upload.php --create-table
php user_upload.php --file ../examples/users.csv --dry-run
php user_upload.php --file ../examples/users.csv
```

`--dry-run` performs parsing, normalization, validation, and database duplicate reads with zero writes. Invalid CSV records are normal reported results; unreadable input, invalid arguments, or infrastructure failures return a non-zero exit code.

### Browser and CLI workflow

The browser and CLI use the same `UserImportService`, so normalization, validation, duplicate checks, transactions, and confirmed database writes are identical. Their confirmation flow is intentionally different:

- The browser previews the file and pauses until the user selects **Import**.
- The CLI is non-interactive. `--dry-run` is the preview command, while the command without `--dry-run` revalidates the original CSV and imports immediately.

For a review-before-import CLI workflow, run these as two explicit commands:

```powershell
php user_upload.php --file "C:\Users\Danrave\Downloads\users.csv" --dry-run
php user_upload.php --file "C:\Users\Danrave\Downloads\users.csv"
```

The second command never trusts cached dry-run output. It processes the CSV again so database changes that occurred after preview are handled safely. Its final output lists only PostgreSQL-confirmed inserts as imported and reports every rejected row with its original CSV row number and reason.

On Windows PowerShell, open a terminal in the repository and run:

```powershell
docker compose up -d
Set-Location backend
php user_upload.php --help
php user_upload.php --file "C:\Users\Danrave\Downloads\users.csv" --dry-run
php user_upload.php --file "C:\Users\Danrave\Downloads\users.csv"
```

If `php` is not on `PATH` on this machine, use the installed executable directly:

```powershell
& "$env:LOCALAPPDATA\Programs\php83\php.exe" user_upload.php --help
& "$env:LOCALAPPDATA\Programs\php83\php.exe" user_upload.php --file "C:\Users\Danrave\Downloads\users.csv" --dry-run
```

The regular import command prints the confirmed imported users and all rejected rows with their original CSV row numbers and reasons. `--create-table` rebuilds the table and deletes its existing data, so reserve it for an intentional reset.

## API

```text
POST /api/imports/preview   multipart/form-data field: file
POST /api/imports           multipart/form-data field: file
```

Row validation returns HTTP 200 with structured errors. Missing uploads, non-CSV files, or invalid CSV structure return a consistent JSON error with a 4xx status. Unexpected errors return a generic 5xx response without stack traces or credentials.

## Quality checks

Backend:

```bash
cd backend
composer test
composer analyse
composer cs:check
composer cs:fix
```

To include PostgreSQL integration tests locally, set these variables before `composer test`:

```dotenv
TEST_DB_HOST=127.0.0.1
TEST_DB_PORT=5432
TEST_DB_NAME=user_import_test
TEST_DB_USER=user_import
TEST_DB_PASSWORD=local_dev_password
```

Frontend:

```bash
cd frontend
npm run lint
npm run test
npm run build
```

GitHub Actions runs formatting, PHPStan level 8, PHPUnit against PostgreSQL, ESLint, Vitest, and the production frontend build on pushes and pull requests.

### Verifying an import

Successful writes can be checked in three ways:

1. Expand the imported and rejected detail sections on the final browser screen.
2. Review the detailed output from the CLI import command.
3. Run the PostgreSQL integration tests, which compare each result with the actual rows stored in PostgreSQL.

For a manual database check using the configured Compose service:

```powershell
docker compose exec postgres psql -U user_import -d user_import -c "SELECT id, name, surname, email FROM users ORDER BY id;"
```

## Design decisions

### Why Slim

Only two small HTTP endpoints are needed. Slim supplies routing and error middleware without introducing full-stack framework conventions unrelated to this challenge.

### Why PDO instead of an ORM

The application has one table and a few explicit queries. PDO keeps persistence understandable, while the repository boundary keeps SQL out of the import service and HTTP/CLI adapters.

### Why one shared `UserImportService`

HTTP and CLI must agree. A single service owns normalization order, validation, in-file duplicate semantics, batched database lookups, transactions, and conflict handling.

### Why import revalidates the file

Preview data can become stale or be modified in a browser. Import sends and processes the original CSV again, keeping the backend authoritative without server-side sessions or staging tables.

### Why PostgreSQL still has a unique constraint

Application checks provide useful preview feedback. The unique database constraint remains the final integrity guarantee, and `ON CONFLICT DO NOTHING` safely handles a concurrent insert between preview and import.

### Loading and caching

The UI has explicit `idle`, `selected`, `previewing`, `preview`, `importing`, `complete`, and `error` states. Buttons are disabled during work, progress text is announced, and failures retain enough state to retry or return to the preview.

Successful previews are cached in memory for 30 seconds, up to five file fingerprints, to avoid accidental repeated uploads in the same tab. The cache is cleared after import. Because database state can change, import always revalidates; API responses also send `Cache-Control: no-store` so browsers and shared caches do not retain user data.

### Large CSV trade-off

League CSV iterates source records instead of loading the raw file at once. The web preview intentionally materializes processed rows so it can display them. This is appropriate for the challenge scope but not an unlimited-memory claim.

## Assumptions

- A header row is required. Header names are trimmed and matched case-insensitively.
- Blank CSV fields are returned as row validation errors rather than parser failures.
- Duplicate comparison happens after email normalization. The first occurrence can remain valid; later occurrences are marked `duplicate_in_file`.
- A race-time unique conflict is counted as skipped, while unexpected database failures roll back the transaction.
- Uploaded files remain in PHP-managed temporary storage only for the request and are never moved into the public directory.

## Future improvements

These are intentionally outside the controlled challenge scope:

- Editable preview rows, with explicit edited-data submission and complete server-side revalidation before import.
- Pagination or virtualized rendering for very large previews.
- Configurable upload limits and operational metrics based on real production requirements.
- Staging or background processing for imports that exceed synchronous request limits.
- End-to-end browser tests for deployment environments in addition to the current behavior tests.

## Repository conventions

Development starts from `main` and is implemented on `feat/user-import-application`. Commits use Conventional Commit-style subjects and represent verified, logical slices rather than a single generated snapshot. Runtime secrets, dependencies, build output, temporary uploads, and IDE files are ignored; both lock files are committed.
