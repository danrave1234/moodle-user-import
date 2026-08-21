# Moodle User Import

A CSV user-import application with a React web UI, PHP/Slim API, Symfony Console CLI, and PostgreSQL database. The web and CLI adapters share the same import business logic.

## Features

- Accepts `name`, `surname`, and `email` CSV columns in any order; extra columns are ignored.
- Trims values, title-cases names with multibyte-safe functions, and lowercases email addresses.
- Reports required-field, invalid-email, in-file duplicate, and database duplicate errors by CSV row.
- Previews valid and invalid users before importing.
- Reprocesses the original file during import instead of trusting browser preview data.
- Uses a transaction and PostgreSQL `UNIQUE` constraint with conflict-safe inserts.
- Supports browser preview and CLI dry-run workflows with clear loading and error states.

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

`UserImportService` owns the shared parsing, normalization, validation, duplicate checking, and transaction workflow. Slim actions and the Symfony command are adapters; persistence is isolated behind `UserRepository` and `PdoUserRepository`.

## Technology

- **Backend:** PHP 8.3+, Slim 4, Symfony Console, League CSV, PDO
- **Database:** PostgreSQL
- **Frontend:** React, TypeScript, Vite
- **Testing and quality:** PHPUnit, PHPStan, PHP-CS-Fixer, Vitest, React Testing Library, ESLint
- **Local development:** Docker Compose
- **CI:** GitHub Actions

## Requirements

- PHP 8.3 or newer with `mbstring`, `PDO`, and `pdo_pgsql`
- Composer 2
- Node.js 24 LTS and npm (tested development version)
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

### Database configuration

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

Import uploads and reprocesses the original CSV; preview rows are never submitted as trusted input.

## CLI

Run commands from `backend`:

```bash
php user_upload.php --help
php user_upload.php --create-table
php user_upload.php --file ../examples/users.csv --dry-run
php user_upload.php --file ../examples/users.csv
```

- `--help` displays command usage and options.
- `--create-table` rebuilds the users table and deletes its current data.
- `--dry-run` parses, normalizes, validates, and checks database duplicates without writing.
- `--file` without `--dry-run` processes the CSV again and imports valid records.

Dry-run and import are separate CLI commands. Import does not trust earlier dry-run output; it revalidates the file and reports confirmed inserts and rejected rows.

## API

```text
POST /api/imports/preview   multipart/form-data field: file
POST /api/imports           multipart/form-data field: file
```

Row validation returns HTTP 200 with structured errors. Missing uploads, non-CSV files, or invalid CSV structure return a consistent JSON error with a 4xx status. A database connection failure returns a sanitized JSON `503`; unexpected failures return a generic `500` without stack traces, DSNs, or credentials.

## Quality checks

Backend:

```bash
cd backend
composer test
composer analyse
composer cs:check
```

Use `composer cs:fix` to apply formatting fixes during development.

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

GitHub Actions runs the equivalent formatting, static-analysis, test, lint, and build checks. PostgreSQL integration tests verify results against stored rows.

Optionally inspect imported users from the repository root:

```bash
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

Preview data and database state can become stale. Preview and import independently process the original CSV, keeping the backend authoritative. HTTP responses use `Cache-Control: no-store`.

### Why PostgreSQL still has a unique constraint

Application checks provide useful preview feedback. The unique database constraint remains the final integrity guarantee, and `ON CONFLICT DO NOTHING` safely handles a concurrent insert between preview and import.

### Large CSV trade-off

League CSV iterates source records instead of loading the raw file at once. The web preview intentionally materializes processed rows so it can display them. This is appropriate for the challenge scope but not an unlimited-memory claim.

## Assumptions

- A header row is required. Header names are trimmed and matched case-insensitively; headers that collide after normalization are rejected.
- Blank CSV fields are returned as row validation errors rather than parser failures.
- Duplicate comparison happens after email normalization. The first occurrence can remain valid; later occurrences are marked `duplicate_in_file`.
- A race-time unique conflict is counted as skipped, while unexpected database failures roll back the transaction.
- Uploaded files remain in PHP-managed temporary storage only for the request and are never moved into the public directory.

## Future improvements

- Pagination or virtualized rendering for very large previews.
- Configurable upload and row limits based on deployment requirements.
- Staged or background processing when imports exceed synchronous request limits.
