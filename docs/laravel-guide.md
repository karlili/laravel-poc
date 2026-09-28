# Laravel guide

This guide is for developers who are new to Laravel or to this project. It explains how Laravel handles a request, and what each folder in the repository is for. For setup, see the [README](../README.md). For the Azure runtime and the roles and permissions, see [architecture.md](architecture.md).

## Laravel in one page

Laravel is a PHP framework built around the model-view-controller idea. In this project, most pages don't use controllers. They are **Livewire components**: one file holds the PHP class and its Blade template, and Livewire keeps the page interactive without custom JavaScript.

| Concept | What it does | Where you see it here |
|---|---|---|
| Routing | Maps a URL to the code that answers it | `routes/web.php`: `Route::livewire('companies', 'pages::companies.index')` |
| Middleware | Code that runs before and after a request, such as login checks and CSRF protection | `auth`, `verified`, `can:users.manage` and `password.confirm` on routes |
| Service container and providers | The container builds objects and injects their dependencies. Providers configure the app on boot | `app/Providers/AppServiceProvider.php` sets the admin bypass and the morph map |
| Eloquent | The ORM. Each table has a model class | `app/Models/Company.php`, with relations such as `contacts()` and `notes()` |
| Policies and gates | Authorisation rules: who may do what to which record | `app/Policies/CompanyPolicy.php` extends `OwnedRecordPolicy` |
| Blade | The template language. `<x-…>` and `<flux:…>` tags are components | `resources/views/**` |
| Livewire | Server-rendered components that update over AJAX | `resources/views/pages/**` |
| Config and `.env` | `config/*.php` files read environment values with `env()` | `config/crm.php` reads `CRM_DEFAULT_ROLE` |
| Migrations, factories and seeders | Schema changes, fake data and initial data | `database/` |
| Artisan | The command-line tool (`php artisan …`) | `artisan`, `app/Console/Commands` |

Two rules that trip up newcomers:

- **Call `env()` only inside `config/` files.** In production, config is cached (`php artisan optimize`), and `env()` returns `null` everywhere else. In app code, use `config('crm.default_role')`.
- **Don't edit `vendor/`.** It holds the Composer packages and is rebuilt on every install. Change package behaviour through config, a service provider or a published view instead, as `resources/views/flux/` does.

## How a request flows

```
Browser
  │  GET /companies?search=acme
  ▼
public/index.php ─── the only PHP entry point that the web server runs
  │  loads vendor/autoload.php, then bootstrap/app.php
  ▼
bootstrap/app.php ── registers routes, middleware and exception handling
  │  boots the providers listed in bootstrap/providers.php
  ▼
Middleware ───────── session, CSRF, then route middleware: auth, verified
  ▼
Router ───────────── finds the matching route in routes/web.php or routes/settings.php
  │
  ├─▶ Livewire page:  pages::companies.index → resources/views/pages/companies/index.blade.php
  ├─▶ Controller:     media/{media} → app/Http/Controllers/MediaDownloadController.php
  └─▶ Fortify:        GET /login → pages::auth.login view, POST /login → Fortify's controller
  ▼
Your code ────────── authorise (policy) → query (Eloquent) → render (Blade inside a layout)
  ▼
Middleware ───────── on the way out: saves the session and adds cookies
  ▼
Browser receives HTML
```

Step by step:

1. **`public/index.php`** is the web root's front controller. It stops early if the app is in maintenance mode, loads Composer's autoloader and creates the application from `bootstrap/app.php`.
2. **`bootstrap/app.php`** loads `routes/web.php` and `routes/console.php`, and registers the `/up` health check used by Azure. It trusts the proxy headers from Azure's ingress and returns JSON errors for `api/*` and JSON requests.
3. **Service providers** in `bootstrap/providers.php` boot, and so do package providers, which Laravel discovers automatically:
   - `AppServiceProvider` lets admins pass every check (`Gate::before`), maps short type names for polymorphic columns (`enforceMorphMap`), blocks destructive database commands in production and sets the password rules.
   - `FortifyServiceProvider` connects Fortify to the auth views in `resources/views/pages/auth/` and to the actions in `app/Actions/Fortify/`, and sets the login rate limits.
4. **Middleware** runs. The web group handles cookies, the session and CSRF. Route middleware adds the checks on each route, for example `auth` and `verified` on the CRM routes and `can:users.manage` on `admin/users`.
5. **The router** runs one of three kinds of handler:
   - **Livewire page**, which covers most pages. `Route::livewire(...)` points at a single-file component. Route parameters such as `{company}` are resolved into models automatically (route-model binding) and passed to `mount()`.
   - **Controller**. `MediaDownloadController` checks the parent record's policy, then streams the file.
   - **Fortify**. The login, register and password-reset screens are plain Blade forms. Their POST requests go to controllers inside the Fortify package.
6. **Your code** checks access with `$this->authorize('view', $company)`, which calls the policy. It queries through Eloquent and renders Blade inside a layout (`resources/views/layouts/app.blade.php` for signed-in pages, `layouts/auth.blade.php` for the auth screens).
7. **The response** goes back out through the middleware, which saves the session, and reaches the browser as complete HTML.

### After the page loads: Livewire updates

The first response is complete HTML, and Livewire's JavaScript then takes over that part of the page.

- When the user interacts (typing in `wire:model.live` search, clicking `wire:click="sort('name')"` or submitting `wire:submit="save"`), Livewire POSTs the component's signed state to `/livewire/update`.
- The server rebuilds the component, runs the action, and renders the template again. The browser then patches only the parts of the page that changed.
- These requests pass through the same middleware, and the component methods call `authorize()` again, so every action is checked on the server.
- Links with `wire:navigate` load the next page in the background and swap it in without a full reload.
- Child components marked `lazy`, such as `<livewire:notes-thread lazy …>` on the company and contact pages, send a skeleton first (the `@placeholder` block in the component file). They load their real content in a second request, so the record details appear without waiting for notes and attachments.

Livewire bundles Alpine.js for small client-side behaviour, which is why `resources/js/app.js` is empty.

## Folder-by-folder tour

### Application code: `app/`

Everything in `app/` is autoloaded under the `App\` namespace (PSR-4), so `app/Models/Company.php` is `App\Models\Company`.

| Path | Purpose |
|---|---|
| `app/Actions/Attachments` | `StoreAttachment`: the upload validation rules, and copying a Livewire upload into the media library |
| `app/Actions/Fortify` | How Fortify creates users (`CreateNewUser`) and resets passwords (`ResetUserPassword`) |
| `app/Concerns` | Traits that share validation rules for profiles and passwords |
| `app/Console/Commands` | Custom Artisan commands. `EnsureMediaContainer` is `php artisan media:ensure-container` |
| `app/Enums` | `Role` and `Permission`: the permission names, and which role gets which permission |
| `app/Http/Controllers` | Classic controllers. Only `MediaDownloadController` exists, because the pages are Livewire components |
| `app/Livewire/Forms` | Livewire Form objects (`CompanyForm`, `ContactForm`). They hold a form's fields, validation rules and save logic, so the page components stay small |
| `app/Livewire/Actions` | `Logout`, a small invokable action |
| `app/Models` | Eloquent models: `Company`, `Contact`, `Note` and `User` |
| `app/Models/Concerns` | Model traits: `HasOwner` (the `owner_id` relation and `isOwnedBy()`) and `HasAttachments` (the media collection and image thumbnails) |
| `app/Policies` | Authorisation. `OwnedRecordPolicy` holds the shared rule: you may edit if you own the record or have `records.manage-any` |
| `app/Providers` | Configuration that runs on boot. See [How a request flows](#how-a-request-flows) |

Laravel finds policies by naming convention: `App\Models\Company` uses `App\Policies\CompanyPolicy`, with no registration needed.

### Framework plumbing

| Path | Purpose |
|---|---|
| `bootstrap/app.php` | Creates and configures the application: routes, middleware and exceptions |
| `bootstrap/providers.php` | The app's own service providers |
| `bootstrap/cache/` | Generated files (the package manifest, and config and route caches from `php artisan optimize`). Safe to delete; don't commit |
| `config/` | One file per subsystem (`database.php`, `session.php`, `filesystems.php`, …). Package configs such as `permission.php`, `media-library.php`, `activitylog.php`, `livewire.php` and `fortify.php` are published here so they can be changed. `crm.php` holds this app's own settings |
| `routes/web.php` | Browser routes for the dashboard, companies, contacts, media and admin |
| `routes/settings.php` | Settings routes for profile, appearance and security. Loaded from `web.php` |
| `routes/console.php` | Closure-based Artisan commands and scheduled tasks. Currently empty |
| `public/` | The web server's document root: `index.php`, favicons and `robots.txt`. `public/build/` holds compiled assets (git-ignored) |
| `storage/app/` | Files written by the app on the local disk. `public/` is web-visible through the `storage:link` symlink; `private/` is not. Attachments go to Blob Storage (Azurite locally), not here |
| `storage/framework/` | Compiled Blade views, file cache and sessions, and the maintenance-mode flag |
| `storage/logs/` | `laravel.log`. Follow it live with `php artisan pail` |
| `vendor/` | Composer packages. Never edit or commit this folder |

`storage/` and `bootstrap/cache/` must be writable by the web server user. If you see permission errors, see the README's troubleshooting section.

### Frontend: `resources/`

| Path | Purpose |
|---|---|
| `resources/views/pages/` | **Livewire single-file page components**, referenced as `pages::…` (for example `pages::companies.index`). Each file has a `new class extends Component { … }` block on top and the template below |
| `resources/views/pages/auth/` | Login, register, password reset and two-factor screens. Plain Blade forms served by Fortify |
| `resources/views/pages/settings/` | Profile, appearance and security (2FA, passkeys) pages and their modals |
| `resources/views/livewire/` | Reusable Livewire components embedded in pages: `<livewire:notes-thread>` and `<livewire:attachments>` |
| `resources/views/components/` | Anonymous Blade components, used as `<x-app-logo>`, `<x-auth-header>`, `<x-passkey-verify>` and so on. `@props` declares their inputs |
| `resources/views/layouts/` | Page shells. `app.blade.php` wraps signed-in pages in the sidebar layout; `auth.blade.php` wraps the auth screens. Used as `<x-layouts::app>` |
| `resources/views/partials/` | Snippets included with `@include`, such as `head.blade.php` (meta tags, `@vite`, fonts) |
| `resources/views/flux/` | Local overrides of Flux UI components and icons. A file here replaces the one in `vendor/livewire/flux` |
| `resources/views/welcome.blade.php` | The public landing page at `/` |
| `resources/css/app.css` | Tailwind CSS 4 entry point. Imports Flux's stylesheet, tells Tailwind which files to scan (`@source`) and sets the theme colours |
| `resources/js/` | Vite entry points. `app.js` is intentionally empty; `passkeys.js` loads the WebAuthn helper |
| `vite.config.js` | Builds the CSS and JS with `laravel-vite-plugin` and hot reloads Blade changes in development |
| `package.json`, `package-lock.json`, `.npmrc` | Node build dependencies. `node_modules/` is git-ignored |

`<flux:…>` tags come from [Flux](https://fluxui.dev), the Livewire UI kit. They are Blade components, so they render on the server as HTML with Tailwind classes.

### Database: `database/`

| Path | Purpose |
|---|---|
| `database/migrations/` | The schema history, run in filename (timestamp) order. Never edit a migration that has run in production; add a new one |
| `database/factories/` | Fake data generators for tests and seeding, e.g. `Company::factory()->create()` |
| `database/seeders/DatabaseSeeder.php` | Local demo data: the seeded accounts and 12 companies with contacts and notes |
| `database/seeders/ProductionSeeder.php` | Data every environment needs. It calls `RolesAndPermissionsSeeder`, is safe to re-run and runs on every deploy |

### Tests and code quality

| Path | Purpose |
|---|---|
| `tests/Feature/` | Tests that boot the whole app. They make HTTP requests (`$this->get(route(...))`) or drive components (`Livewire::test('pages::companies.form')`). Grouped by area: `Auth`, `Crm`, `Settings`, `Admin` |
| `tests/Unit/` | Tests for plain classes, without the framework booted |
| `tests/TestCase.php` | The base test class, with helpers such as `userWithRole()` |
| `phpunit.xml` | Test settings. Tests use in-memory SQLite, with `RefreshDatabase` giving each test a clean schema |
| `phpstan.neon` | Larastan (PHPStan for Laravel) at level 7 |
| `pint.json` | Code style rules for Laravel Pint |

### Operations and infrastructure

| Path | Purpose |
|---|---|
| `Dockerfile` | Multi-stage image: `base` (PHP-FPM + NGINX), `development` (source mounted from your machine), `vendor` (Composer install), `assets` (Vite build), `production` (the final image) |
| `docker-compose.yml` | Local stack: `app`, `vite`, `mysql`, `azurite` (Blob Storage emulator) and `mailpit` (email catcher) |
| `docker/entrypoint.d/` | Development-only start-up scripts: `composer install`, then migrations and the roles/permissions seeder |
| `docker/vite-dev.sh` | Start command for the `vite` service: install, build, then run the dev server |
| `.devcontainer/` | VS Code dev container definition |
| `infra/terraform/` | Azure infrastructure: Container Apps, MySQL, storage, Key Vault, networking. Per-environment values are in `environments/` |
| `infra/scripts/` | `bootstrap-state.sh` creates the Terraform state storage once |
| `.github/workflows/` | `ci.yml` (lint, analyse, test, build image), `terraform.yml` (plan/apply), `deploy.yml` (build, migrate job, roll out) |
| `.github/dependabot.yml` | Automated dependency update PRs |
| `docs/` | This guide, the architecture overview and the Azure deployment guide |

### Root files

| File | Purpose |
|---|---|
| `artisan` | The CLI entry point: `php artisan <command>` |
| `composer.json`, `composer.lock` | PHP dependencies and Composer scripts (`composer dev`, `composer test`). Commit the lock file |
| `.env`, `.env.example` | Environment settings. `.env` is local and git-ignored; `.env.example` is the committed template. Add every new setting to both |
| `.editorconfig`, `.gitattributes`, `.gitignore`, `.dockerignore` | Editor, git and Docker build settings |

## Where do I put…?

| I want to… | Do this |
|---|---|
| Add a page | Create `resources/views/pages/<area>/<name>.blade.php` (`php artisan make:livewire pages::<area>.<name>`) and add a `Route::livewire(...)` line to `routes/web.php` |
| Add a reusable interactive widget | A Livewire component in `resources/views/livewire/`, embedded with `<livewire:name :prop="$value" />` |
| Add a reusable piece of markup | An anonymous Blade component in `resources/views/components/`, used as `<x-name />` |
| Change the database | `php artisan make:migration add_x_to_companies_table`, then `php artisan migrate` |
| Add a model | `php artisan make:model Deal -mf` (creates the model, migration and factory). Add it to `enforceMorphMap` in `AppServiceProvider` if it will be used in polymorphic relations |
| Control who can do something | Add a method to the model's policy and call `$this->authorize('ability', $model)` in the component. In Blade, use `@can` |
| Add a permission | Add it to `app/Enums/Permission.php` and `Role::permissions()`, then run `php artisan db:seed --class='Database\Seeders\ProductionSeeder'` |
| Add a setting | Add a key to `config/crm.php` that reads `env('…')`, and document it in `.env.example` |
| Validate a form | Put the fields and `rules()` in a Form object in `app/Livewire/Forms/` |
| Ask before deleting something | Put one `<flux:modal name="delete-…">` in the component rather than one per row. The row button calls `confirmDelete($id)`, which authorises, stores the id in a `#[Locked]` property and opens the modal with `Flux::modal(…)->show()`. The modal's button calls `delete()`, which authorises again. See `resources/views/pages/companies/index.blade.php` |
| Load a slow panel after the page | Add `lazy` where it is embedded, and an `@placeholder … @endplaceholder` skeleton in the component. See `resources/views/livewire/notes-thread.blade.php` |
| Add a test | `tests/Feature/<Area>/<Thing>Test.php`, extending `Tests\TestCase` and using `RefreshDatabase` |

## Everyday Artisan commands

Prefix these with `docker compose exec app` when you use the Docker stack.

```bash
php artisan route:list --except-vendor   # every route and what handles it
php artisan make:livewire pages::deals.index
php artisan make:model Deal -mf          # model, migration and factory
php artisan make:policy DealPolicy --model=Deal
php artisan migrate                      # run new migrations
php artisan migrate:fresh --seed         # rebuild the local database with demo data
php artisan tinker                       # REPL: try Eloquent queries, e.g. App\Models\Company::count()
php artisan pail                         # follow the log
php artisan test --filter=CompanyTest    # run some tests
php artisan optimize:clear               # clear config, route and view caches when things look stale
```

## Further reading

- [Laravel documentation](https://laravel.com/docs): start with "Architecture Concepts" → "Request Lifecycle"
- [Livewire documentation](https://livewire.laravel.com/docs)
- [Flux UI components](https://fluxui.dev/components)
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission), [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary), [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
