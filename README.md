# Library Management System — Developer Guide

A library system for a college: catalogue, issue / return / renew / lost, reservations with hold queues, fines,
student self-service, reports and an audit log.

| Layer | Stack |
| --- | --- |
| Frontend | React 18, Vite 6, TypeScript, React Router 6, TanStack Query 5, React Hook Form + Zod, Tailwind CSS 3, Recharts |
| Backend | PHP 8.2, Slim 4 + PHP-DI, layered: Controllers → Services → Repositories, Monolog |
| Database | MySQL 8.4 (InnoDB, utf8mb4), PDO with prepared statements only |
| Auth | JWT access token (15 min) + rotating refresh token in an httpOnly `SameSite=Strict` cookie (7 days), Argon2id |
| Tooling | PHPUnit 11, PHPStan level 6, PHP_CodeSniffer (PSR-12), ESLint 9, Prettier, Vitest + Testing Library |

Contents: [Quick start (Docker)](#quick-start-docker) · [Running without Docker](#running-without-docker) ·
[Daily workflow](#daily-workflow) · [Project layout](#project-layout) · [Backend guide](#backend-guide) ·
[Frontend guide](#frontend-guide) · [Tests and quality checks](#tests-and-quality-checks) ·
[Business rules](#business-rules) · [API](#api) · [Troubleshooting](#troubleshooting) · [Known limitations](#known-limitations)

---

## Quick start (Docker)

Needs only Docker Desktop (or Docker Engine) with Compose v2.24+. PHP, Composer, MySQL and Node all run in containers.

```bash
cp .env.example .env
# Replace every "change_me" value. For the two JWT secrets:  openssl rand -hex 32
docker compose up -d --build
```

The first start takes about a minute: it installs dependencies and loads the schema and sample data.

| Service | URL |
| --- | --- |
| Web app (Vite dev server) | <http://localhost:5173> |
| API | <http://localhost:8080/api/v1> (health check: `/api/v1/health`) |
| phpMyAdmin | <http://localhost:8081> |
| Mailpit (catches every outgoing email) | <http://localhost:8025> |
| MySQL | `localhost:3307` (user/password from `.env`) |

The `scheduler` container runs the hourly overdue / reservation-expiry job.

### Sample logins

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@library.test` | `Admin@123` |
| Librarian | `librarian@library.test` | `Librarian@123` |
| Student | `student1@library.test` … `student20@library.test` | `Student@123` |

`student19` and `student20` have unpaid fines, so they can't borrow, which is useful for testing that rule.
The sample catalogue has 100 books with real covers from Open Library (98 of them; two have none).

---

## Running without Docker

Yes, it runs without Docker. You need:

| Tool | Version | Notes |
| --- | --- | --- |
| PHP | 8.2+ | Extensions: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`, `openssl`, and Argon2 support |
| Composer | 2.x | |
| **MySQL** | **8.0+** | **MariaDB will not work** (see below) |
| Node.js | 20+ | npm 10+ |

> **Why not MariaDB / XAMPP?** The schema and queries use MySQL 8 features MariaDB doesn't support:
> `SELECT … FOR UPDATE OF <table>` (row locking during issue/return) and a unique index on a generated column
> (the database-level guard against issuing one copy twice). XAMPP and WAMP ship MariaDB, so install MySQL 8
> separately ([MySQL Installer for Windows](https://dev.mysql.com/downloads/installer/), `brew install mysql`,
> or `apt install mysql-server`). Laragon can also be switched to MySQL 8.

Check PHP before you start:

```bash
php -v
php -m | grep -iE "pdo_mysql|curl|fileinfo|mbstring|openssl"
# Windows (PowerShell/cmd): php -m | findstr /i "pdo_mysql curl fileinfo mbstring openssl"
php -r "var_dump(defined('PASSWORD_ARGON2ID'));"     # must print bool(true)
```

On Windows, enable the missing extensions in `php.ini` (`extension=pdo_mysql`, `extension=curl`, …). The official
Windows PHP builds already include Argon2.

### 1. Database

```sql
-- in the mysql client, as root
CREATE DATABASE library      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE library_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;   -- for the integration tests
CREATE USER 'library'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON library.*      TO 'library'@'localhost';
GRANT ALL PRIVILEGES ON library_test.* TO 'library'@'localhost';
```

```bash
mysql -u library -p library < database/schema.sql
mysql -u library -p library < database/seed.sql      # optional sample data
```

### 2. Backend

Create `backend/.env`. The API reads it automatically when it isn't running in Docker:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:5173
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=library
DB_USER=library
DB_PASS=choose-a-password
DB_TEST_NAME=library_test
JWT_SECRET=<openssl rand -hex 32>
JWT_REFRESH_SECRET=<openssl rand -hex 32>
CORS_ORIGIN=http://localhost:5173
TRUST_PROXY=true
# Leave empty to write emails to the log instead of sending them (or point it at a local Mailpit/SMTP server)
SMTP_HOST=
```

Everything else falls back to the defaults listed in [.env.example](.env.example).

```bash
cd backend
composer install
php bin/migrate.php                       # applies any pending migrations
php -S localhost:8080 -t public           # API at http://localhost:8080/api/v1
```

PHP's built-in server is fine for development. In production use Apache (the bundled `.htaccess` files work as-is)
or nginx + PHP-FPM, with `backend/public` as the document root.

### 3. Frontend

```bash
cd frontend
npm ci
npm run dev                               # http://localhost:5173, proxies /api and /uploads to localhost:8080
```

### 4. Scheduled job (optional)

Run `php backend/bin/mark_overdue.php` once an hour. Use cron on Linux/macOS:
`0 * * * * php /path/to/backend/bin/mark_overdue.php`. On Windows, add a Task Scheduler task that runs `php.exe` with
that script.

---

## Daily workflow

| You changed… | Docker | Without Docker |
| --- | --- | --- |
| React / CSS | Nothing to do — Vite hot-reloads | Same |
| PHP code | `docker compose up -d --build api scheduler` (~15 s) | Nothing — the next request uses it |
| `frontend/package.json` | `docker compose restart web` (the container has its own `node_modules`) | `npm install` |
| `backend/composer.json` | Rebuild the API image (as for PHP code) | `composer install` |
| `.env` | `docker compose up -d` (recreates containers with the new values) | Restart `php -S` |
| Database schema | Add a migration (see [Migrations](#migrations)), then rebuild the API | `php bin/migrate.php` |

**Why rebuild after PHP changes?** In Docker, the backend code is copied into the image instead of being shared from your
disk. With Docker Desktop on Windows, a shared folder made every request take about 9 s; inside the image it's about 20 ms.

Other everyday commands:

```bash
docker compose logs -f api                                     # Apache + PHP errors
docker compose exec api tail -f logs/app-$(date +%F).log      # app log (JSON lines)
docker compose exec api php bin/fetch_covers.php              # fill missing covers from Open Library
docker compose down -v && docker compose up -d                # reset the database to the sample data
```

Outgoing email (password resets, reminders, reservation alerts) shows up in Mailpit at <http://localhost:8025>.

---

## Project layout

```
├── backend/
│   ├── bin/                 migrate.php · mark_overdue.php (cron) · generate_seed.php · fetch_covers.php
│   ├── config/              container.php (DI wiring) · routes.php (every route and its role guard)
│   ├── public/              index.php (single entry point) · uploads/covers (PHP execution disabled)
│   ├── src/
│   │   ├── Controllers/     thin HTTP layer: parse request → call a service → return JSON
│   │   ├── Services/        business rules (CirculationService, ReservationQueue, AuthService, IsbnLookupService…)
│   │   ├── Repositories/    one interface + one PDO implementation per aggregate
│   │   ├── Models/          small typed entities used by the services
│   │   ├── Middleware/      auth, roles, rate limit, JSON body, security headers, request log, error handler
│   │   ├── Validators/      Field + Rules: declarative input validation
│   │   ├── Exceptions/      ApiException and subclasses → HTTP status + error code
│   │   ├── Helpers/         Database (PDO + transactions), JWT, CSV, pagination, Env, Clock
│   │   └── Kernel.php       builds the container and the Slim app (middleware order lives here)
│   ├── tests/               Unit/ (mocked repositories) · Integration/ (real MySQL, incl. a concurrency test)
│   ├── openapi.yaml · postman_collection.json
│   └── Dockerfile
├── frontend/src/
│   ├── api/                 client.ts (axios, token refresh) · endpoints.ts (one typed function per endpoint)
│   ├── components/          ui.tsx primitives · DataTable · Modal · Charts · NotificationBell · BookSpines
│   ├── features/            forms with logic: books (BookFormModal, CoverPicker), students, circulation
│   ├── layouts/             PublicLayout · DashboardLayout (sidebar navigation is defined here)
│   ├── pages/               public/ · student/ · admin/ · shared/ (catalogue, book page, profile…)
│   ├── routes/              AppRoutes (all routes) · ProtectedRoute (role guard)
│   ├── store/               auth context + provider · theme hook
│   ├── hooks/ types/ utils/
│   └── __tests__/
├── database/                schema.sql · seed.sql (generated) · migrations/ · docker/ (test-DB init)
├── docker-compose.yml
└── .env.example
```

---

## Backend guide

### How a request flows

```
public/index.php → Kernel::app()
  RequestLog → CORS → SecurityHeaders → ErrorHandler → JsonBody → Routing
    → [AuthMiddleware → RoleMiddleware] (per route group, config/routes.php)
      → Controller → Service (validation, rules, transaction, audit) → Repository (SQL)
```

Any `ApiException` thrown anywhere becomes `{ "error": { "code", "message", "details" } }` with its HTTP status.
Anything else becomes a generic 500, with details only when `APP_DEBUG=true`.

### Adding an endpoint

1. **Route:** add it to `config/routes.php` inside the group for the right audience (public, any signed-in user,
   student, staff, admin). The group decides who may call it.
2. **Controller:** a method with `(ServerRequestInterface $request, ResponseInterface $response, string $id)`.
   Route parameters arrive by name. Return `Http::json(...)`.
3. **Validation:** describe the payload in `Validators/Rules.php` with `Field::*`, then call
   `Validator::validate($input, Rules::yourThing())`. Invalid input becomes a 422 with per-field messages.
4. **Service:** put the business logic here. Wrap multi-step writes in `$db->transaction(...)`. Log staff writes
   with `$audit->log('thing.action', 'entity', $id, [...])`. Defer emails with `$db->afterCommit(...)`
   (`NotificationService::notify(..., email: true)` already does).
5. **Repository:** add the method to the interface and its implementation. Use named placeholders, and **never
   reuse a placeholder name** in one query (native prepares reject it): write `:q1, :q2`, not `:q` twice.
6. **Errors:** throw `NotFoundException`, `ValidationException`, or `ConflictException('message', 'ERROR_CODE')`
   for business-rule violations. The frontend shows `message` and can branch on `code`.
7. **Docs and tests:** update `openapi.yaml` (and `postman_collection.json`), and add a unit or integration test.

### Conventions

- `declare(strict_types=1)`, PSR-12, and PHPStan level 6 must pass. Arrays are typed with the aliases `Row`
  (`array<string, mixed>`) and `Page` (a paginated result), defined in `phpstan.neon`.
- **Lock order** inside circulation transactions is student → issue → copy → book. Keep it when adding workflows,
  or concurrent requests can deadlock.
- Use `Clock` (injected) for "today" instead of `date()`, so tests can fix the date.
- List endpoints take `page`, `limit` (≤ 100), `sort`, `order` and filters through `ListQuery`. Sortable columns are
  whitelisted in each repository interface's `SORTABLE`.

### Migrations

`database/schema.sql` is the baseline. Put each later change in `database/migrations/NNN_description.sql`.
`php bin/migrate.php` applies pending files in order and records them in `schema_migrations`. It runs automatically
when the API container starts.

### Sample data

`database/seed.sql` is generated. Don't edit it by hand:

```bash
docker compose exec api sh -c "php bin/generate_seed.php --covers > /tmp/seed.sql" && docker compose cp api:/tmp/seed.sql database/seed.sql
docker compose down -v && docker compose up -d
```

Passwords in it are Argon2id hashes. Dates are relative to the day it's loaded, so loans are always due soon or overdue.
`--covers` looks each book up on Open Library (about 2 minutes, needs internet).

---

## Frontend guide

- **API calls:** add a typed function to `api/endpoints.ts` and its types to `types/index.ts`. Use it through
  TanStack Query (`useQuery` / `useMutation`). `api/client.ts` attaches the access token and, on a 401,
  refreshes the session once (shared across parallel requests) before retrying.
- **Errors:** `getErrorMessage(error)` gives the API's message. `applyServerErrors(error, setError, fields)` puts 422
  messages under the matching form fields.
- **Forms:** React Hook Form + Zod, with each input wrapped in `<Field label error hint>` (it wires up ids and ARIA).
- **Lists:** `useListState` (page / sort / filters) + `<DataTable>`. On phones `DataTable` shows each row as a card
  (first column as the title, `actions` column at the bottom), so put the most identifying column first.
- **Pages:** create the page in `pages/…`, add a lazy route in `routes/AppRoutes.tsx`, and a sidebar entry in
  `layouts/DashboardLayout.tsx` (`NAV` groups, each with allowed roles).

### Design system

Defined in `tailwind.config.ts` and `src/index.css`:

| Token / class | Use |
| --- | --- |
| `brand-*` (library green) | Primary buttons, active navigation, the sidebar (`brand-950`) |
| `accent-*` (brass) | Small highlights: active-nav marker, logo mark, banner buttons |
| `bg-paper` | Page background (warm off-white); dark mode uses `stone-950` |
| `stone-*` | All neutral text, borders and surfaces (use this, not `slate`/`gray`) |
| `font-display` (Fraunces) | `h1`/`h2` automatically, plus big numbers and the brand name |
| `.card` `.btn-primary` `.btn-secondary` `.btn-ghost` `.btn-sm` `.input` `.label` `.link` `.eyebrow` | Component classes |
| `BookCover` | Every book image (shadow, failed-image fallback, cloth-bound placeholder; `showTitle` for large sizes) |

Chart colours live in `components/Charts.tsx`. They were checked for colour-blind safety in both light and dark mode.
Re-check them before changing the colours.

Check every new screen at **360 px**, **820 px** and **1440 px** width, in light and dark mode. Nothing should scroll
sideways at any width.

---

## Tests and quality checks

```bash
# Backend — inside the API container (rebuild first if PHP changed: docker compose up -d --build api)
docker compose exec api composer test        # PHPUnit: unit + integration (resets the library_test database)
docker compose exec api composer stan        # PHPStan level 6
docker compose exec api composer cs          # PHP_CodeSniffer PSR-12

# Auto-fix PHP style in YOUR files (the API container only holds a copy of the code)
docker run --rm --entrypoint sh -v "$PWD/backend:/app" -w /app library-management-api -c "composer install -q && vendor/bin/phpcbf"

# Frontend
cd frontend
npm test                 # Vitest
npm run lint             # ESLint
npm run typecheck        # tsc --noEmit
npm run format           # Prettier (format:check to only check)
npm run build            # production build → frontend/dist
```

Without Docker, run the same `composer test|stan|cs|cs-fix` commands from `backend/`.

The integration tests need the `library_test` database. One of them starts 8 PHP processes that try to issue the same copy
at the same instant, and checks that exactly one succeeds.

---

## Business rules

Defaults come from `.env`; admins can change them under **Settings**.

- **Loans** last `LOAN_DAYS` (14). A student may hold `MAX_BOOKS_PER_STUDENT` (5) books; a per-student limit can override it.
- **Issuing is blocked** when the student is suspended, has unpaid fines, is at their limit, already has this title, or
  every free copy is held for another student's reservation.
- **Renewal**: up to `MAX_RENEWALS` (3) times; not when overdue or when someone else has reserved the title.
- **Fines**: days late × `FINE_PER_DAY` (₹5), created on return. Marking a loan lost charges `LOST_BOOK_FEE` (₹500)
  plus any overdue fine.
- **Reservations**: a returned copy is held for the next student in the queue for `RESERVATION_HOLD_HOURS` (48), and
  the student is notified. Holds that aren't collected expire and pass to the next student.
- **Integrity**: issue / return / renew / lost each run in one transaction with row locks. A unique index also prevents
  two active loans of one copy. Every staff write is recorded in `audit_logs`.

---

## API

Everything is under `/api/v1`. The full contract is in [backend/openapi.yaml](backend/openapi.yaml) (OpenAPI 3). For
Postman, import [backend/postman_collection.json](backend/postman_collection.json) and run **Sign in** first; the token
is saved automatically.

| Area | Endpoints |
| --- | --- |
| Auth | `POST /auth/login` `/auth/refresh` `/auth/logout` `/auth/forgot-password` `/auth/reset-password` · `GET /auth/me` · `PUT /auth/change-password` |
| Catalogue | `GET /books` `/books/{id}` (public) · staff: `POST/PUT /books`, `POST /books/{id}/copies`, `POST/DELETE /books/{id}/cover`, `POST /books/import`, `GET /copies/{barcode}`, `GET /isbn-lookup/{isbn}` · admin: `DELETE /books/{id}` · categories & authors CRUD |
| Students (staff) | CRUD · `POST /students/{id}/suspend` `/activate` · `POST /students/import` · `GET /students/{id}/history` · `GET /students/by-roll/{rollNo}` |
| Circulation (staff) | `POST /issues` · `GET /issues` · `POST /issues/{id}/return` `/renew` `/mark-lost` `/remind` · `GET /issues/{id}/fine-preview` |
| Reservations | student `POST /reservations` · `DELETE /reservations/{id}` · staff `GET /reservations`, `POST /reservations/{id}/fulfil` |
| Fines | `GET /fines` · `POST /fines/{id}/pay` · admin `POST /fines/{id}/waive` |
| Self-service | `GET /me/dashboard` `/me/issues` `/me/reservations` `/me/fines` `/me/notifications` · `PUT /me/profile` |
| Reports (staff) | `GET /reports/summary` `/popular-books` `/top-categories` `/overdue` `/monthly-issues` (`?format=csv` downloads) |
| Admin | `GET /audit-logs` · `GET/PUT /settings` |

**ISBN lookup and covers** use [Open Library](https://openlibrary.org/developers/api), which is free and needs no key.
Open Library is crowd-edited, and some popular ISBNs carry spam records; the lookup treats those as "not found". The
only external cover links the API accepts are `covers.openlibrary.org` ones.

---

## Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| Every API request takes seconds (Docker on Windows/macOS) | Someone bind-mounted `./backend` into the API container again. Keep the code in the image (see [Daily workflow](#daily-workflow)). |
| My PHP change has no effect (Docker) | The image holds a copy of the code: `docker compose up -d --build api scheduler`. |
| Vite shows `Failed to resolve import` / `ENOENT … node_modules` after adding a package | The web container has its own `node_modules`: `docker compose restart web`. |
| "Too many attempts. Please wait a minute" on sign-in | Login is limited to 5 attempts per minute per IP. Wait 60 s. |
| API answers `403 CORS_ORIGIN_NOT_ALLOWED` | You're calling the API directly from a page on another origin. Use the Vite dev server (it proxies `/api`), or add that origin to `CORS_ORIGIN`. |
| Testing on a phone over Wi-Fi | Open `http://<your-PC-IP>:5173`. This works because the Vite proxy makes API calls same-origin. |
| `docker compose up` fails with "port is already allocated" | Another program uses 3307, 8080, 8081, 5173, 8025 or 1025. Stop it, or change the left-hand port in `docker-compose.yml`. |
| `SQLSTATE … FOR UPDATE OF` syntax error | You're on MariaDB. Use MySQL 8 (see [Running without Docker](#running-without-docker)). |
| Password-reset emails don't arrive | In Docker, check Mailpit (<http://localhost:8025>). Without Docker and with `SMTP_HOST` empty, emails are only written to `backend/logs`. |
| A book shows the green placeholder instead of a cover | No cover is set, or the image failed to load. Run `bin/fetch_covers.php`, or upload one under *Edit book*. |

---

## Known limitations

- Staff accounts (admins and librarians) come from the seed or the database. There is no staff-management screen yet.
- Barcodes show as text; there is no label printing. Keyboard-style barcode scanners work on the issue desk.
- In Docker the frontend runs on the Vite dev server. For production, build it (`npm run build`), serve `frontend/dist`
  from a web server that sets its own CSP, and proxy `/api` and `/uploads` to PHP.
- Fines are paid at the desk; there is no online payment.
- Dates are in UTC and amounts in INR.
- The sample-data ISBNs are made up (valid checksums, not real registrations).
