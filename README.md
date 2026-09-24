# Laravel CRM

A customer relationship management (CRM) app built on **Laravel 13** with Livewire 4 and Flux. It covers:

- Companies, contacts and notes, with search, filters, sorting and a soft-delete archive.
- Document and image attachments, with image thumbnails and access-checked downloads.
- Email/password sign-up and login, email verification, two-factor authentication and passkeys (Laravel Fortify).
- **Sign in with Microsoft**: single sign-on against one Microsoft Entra ID tenant.
- Role-based access with ownership rules (admin, manager, sales and viewer).
- An activity log that records changes to CRM records.
- Local development with Docker Compose and hosting on **Azure Container Apps**, defined in Terraform and deployed by GitHub Actions.

| Doc | What's in it |
|---|---|
| [docs/architecture.md](docs/architecture.md) | How the pieces fit together, and the roles and permissions |
| [docs/entra-setup.md](docs/entra-setup.md) | Registering the app in Microsoft Entra ID for SSO |
| [docs/deployment-azure.md](docs/deployment-azure.md) | Terraform, GitHub OIDC and the first deploy to Azure |

## Stack

| Area | Choice |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| UI | Livewire 4 single-file components, Flux UI, Tailwind CSS 4, Vite |
| Auth | Laravel Fortify, Socialite + `socialiteproviders/microsoft-azure` |
| Authorisation | `spatie/laravel-permission` and Laravel policies |
| Files | `spatie/laravel-medialibrary` on Azure Blob Storage (`azure-oss/storage-blob-laravel`) |
| Audit | `spatie/laravel-activitylog` |
| Database | MySQL 8.4 |
| Cache, sessions, queue | Redis 7 |
| Quality | PHPUnit, Larastan (level 7), Pint |

## Project layout

| Path | Contents |
|---|---|
| `app/Models` | `Company`, `Contact`, `Note` and `User` |
| `app/Policies` | Authorisation rules, including record ownership |
| `app/Enums` | `Role` and `Permission` enums |
| `app/Http/Controllers` | Microsoft SSO callback and attachment downloads |
| `resources/views/pages` | Livewire page components (dashboard, companies, contacts, admin, settings, auth) |
| `routes/web.php` | Application routes |
| `database/seeders` | Roles, permissions and demo data |
| `compose.yaml`, `Dockerfile` | Local stack and the production image |
| `infra/terraform` | Azure infrastructure |
| `.github/workflows` | CI, Terraform and deploy pipelines |

## Getting started with Docker (recommended)

### Prerequisites

- Docker with Compose v2 (Docker Desktop, or Docker Engine with the compose plugin).
- Free ports 8000, 3306, 8025 and 10000 (change them in `.env` with `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_UI_PORT` and `FORWARD_AZURITE_PORT`).

You do not need PHP, Composer or Node on your machine; everything runs in containers.

### First run

```bash
# 1. Create your environment file. The defaults match the Compose services.
cp .env.example .env

# 2. Build the image and start the stack (app, queue, scheduler, MySQL, Redis, Azurite, Mailpit).
docker compose up -d --build

# 3. Install PHP dependencies and generate the app key.
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 4. Create the tables and load demo data.
docker compose exec app php artisan migrate --seed

# 5. Create the blob container for attachments in Azurite.
docker compose exec app php artisan media:ensure-container

# 6. Build the frontend assets.
docker compose run --rm vite npm ci
docker compose run --rm vite npm run build
```

Open http://localhost:8000 and sign in with one of the seeded accounts below.

### Seeded accounts

The seeder creates these accounts, all with the password `password`, plus 12 demo companies with contacts and notes:

| Email | Role |
|---|---|
| admin@example.com | admin |
| sales@example.com | sales |
| viewer@example.com | viewer |

### Local services

| Service | URL |
|---|---|
| App | http://localhost:8000 |
| Mailpit (verification and password reset emails) | http://localhost:8025 |
| MySQL | localhost:3306 (`crm` / `secret`) |
| Azurite (Blob Storage) | localhost:10000 |

Compose runs the queue worker (`queue`) and scheduler (`scheduler`) as separate containers from the same image. Image thumbnails are generated on the queue, so keep the `queue` container running.

### Day-to-day commands

```bash
docker compose up -d                          # start the stack
docker compose --profile vite up -d vite      # Vite dev server with hot reload (http://localhost:5173)
docker compose logs -f app queue              # follow logs
docker compose exec app php artisan tinker    # REPL
docker compose down                           # stop the stack
docker compose down -v                        # stop and delete the database, Redis and blob data
```

To start again from a clean database: `docker compose exec app php artisan migrate:fresh --seed`.

## Running without Docker

If you prefer PHP on your machine, you need PHP 8.4 with the `bcmath`, `exif`, `gd`, `intl` and `pdo_sqlite` (or `pdo_mysql`) extensions, Composer 2 and Node 22.

1. Copy the environment file and adjust it for a setup without the Compose services:

   ```bash
   cp .env.example .env
   ```

   ```dotenv
   DB_CONNECTION=sqlite          # and remove or comment out the other DB_* lines
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   MEDIA_DISK=media              # store attachments on the local disk instead of Azurite
   MAIL_MAILER=log               # write emails to storage/logs/laravel.log
   ```

2. Install dependencies, create the database and build assets:

   ```bash
   composer install
   php artisan key:generate
   touch database/database.sqlite
   php artisan migrate --seed
   npm install
   npm run build
   ```

3. Start the web server, queue worker, log viewer and Vite together:

   ```bash
   composer dev
   ```

   Then open http://localhost:8000.

## Enabling Sign in with Microsoft

SSO is off by default. Register an app in Microsoft Entra ID as described in [docs/entra-setup.md](docs/entra-setup.md), then set these in `.env`:

```dotenv
AUTH_MICROSOFT_ENABLED=true
ENTRA_TENANT_ID=...
ENTRA_CLIENT_ID=...
ENTRA_CLIENT_SECRET=...
```

New sign-ups and first-time Microsoft sign-ins get the role set in `CRM_DEFAULT_ROLE` (default `viewer`). An admin can change roles under **Administration → Users**.

## Tests and code quality

```bash
docker compose exec app php artisan test        # PHPUnit (SQLite in memory)
docker compose exec app vendor/bin/pint         # code style
docker compose exec app vendor/bin/phpstan      # static analysis
docker compose exec app composer test           # all of the above, as CI runs them
```

CI (`.github/workflows/ci.yml`) runs the same checks against MySQL 8.4 and builds the production Docker image.

## Troubleshooting

- **`Vite manifest not found`**: build the assets (`npm run build`) or start the Vite dev server.
- **Attachment uploads fail**: make sure Azurite is running and you ran `php artisan media:ensure-container`, or set `MEDIA_DISK=media`.
- **Image thumbnails never appear**: the queue worker is not running. Check `docker compose ps queue`.
- **No verification email**: look in Mailpit at http://localhost:8025.
- **Permission errors on `storage/`**: set `UID` and `GID` to your user's IDs before building (`UID=$(id -u) GID=$(id -g) docker compose up -d --build`).

## Deploying

See [docs/deployment-azure.md](docs/deployment-azure.md). In short:

- Terraform in `infra/terraform` creates the Azure resources.
- `.github/workflows/terraform.yml` plans and applies infrastructure changes.
- `.github/workflows/deploy.yml` builds the image, runs migrations as a Container Apps job, then rolls out the web app, worker and scheduler.
