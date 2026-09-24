# Laravel CRM

A CRM built on **Laravel 13** with Livewire 4 and Flux. It covers:

- Companies, contacts and notes, with search, filters, sorting and a soft-delete archive.
- Document and image attachments, with image thumbnails and access-checked downloads.
- Email/password sign-up and login, email verification, two-factor authentication and passkeys (Laravel Fortify).
- **Sign in with Microsoft**: single sign-on against one Microsoft Entra ID tenant.
- Role-based access with ownership rules (admin, manager, sales and viewer).
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
| Quality | PHPUnit, Larastan (level 7), Pint |

## Run locally with Docker

You need Docker with Compose v2.

```bash
cp .env.example .env
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan media:ensure-container   # creates the Azurite blob container

# Frontend: build once, or run the Vite dev server with hot reload.
docker compose run --rm vite npm ci
docker compose run --rm vite npm run build
# docker compose --profile vite up -d vite
```

| Service | URL |
|---|---|
| App | http://localhost:8000 |
| Mailpit (verification emails) | http://localhost:8025 |
| MySQL | localhost:3306 (`crm` / `secret`) |
| Azurite (Blob Storage) | localhost:10000 |

The seeder creates these accounts, all with the password `password`:

| Email | Role |
|---|---|
| admin@example.com | admin |
| sales@example.com | sales |
| viewer@example.com | viewer |

Compose runs the queue worker (`queue`) and scheduler (`scheduler`) as separate containers from the same image. Image thumbnails are generated on the queue.

## Tests and code quality

```bash
docker compose exec app php artisan test        # PHPUnit (SQLite in memory)
docker compose exec app vendor/bin/pint         # code style
docker compose exec app vendor/bin/phpstan      # static analysis
```

CI (`.github/workflows/ci.yml`) runs the same checks against MySQL 8.4 and builds the production Docker image.

## Deploying

See [docs/deployment-azure.md](docs/deployment-azure.md). In short:

- Terraform in `infra/terraform` creates the Azure resources.
- `.github/workflows/terraform.yml` plans and applies infrastructure changes.
- `.github/workflows/deploy.yml` builds the image, runs migrations as a Container Apps job, then rolls out the web app, worker and scheduler.
