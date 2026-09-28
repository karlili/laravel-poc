# Laravel guide

This guide is for developers who are new to Laravel or to this project. It explains how Laravel handles a request, and what each folder in the repository is for. For setup, see the [README](../README.md). For the Azure runtime and the roles and permissions, see [architecture.md](architecture.md).

## Laravel in one page

Laravel is a PHP framework built around the model-view-controller idea. In this project, controllers load and check the data, and the views are **React pages** connected by **Inertia**. A controller returns `Inertia::render('companies/index', $props)`, and Inertia renders `resources/js/pages/companies/index.tsx` with those props. You don't need to write a separate JSON API.

| Concept | What it does | Where you see it here |
|---|---|---|
| Routing | Maps a URL to the code that answers it | `routes/web.php`: `Route::resource('companies', CompanyController::class)` |
| Middleware | Code that runs before and after a request, such as login checks and CSRF protection | `auth`, `verified`, `can:users.manage` and `password.confirm` on routes |
| Service container and providers | The container builds objects and injects their dependencies. Providers configure the app on boot | `app/Providers/AppServiceProvider.php` sets the admin bypass and the morph map |
| Eloquent | The ORM. Each table has a model class | `app/Models/Company.php`, with relations such as `contacts()` and `notes()` |
| Policies and gates | Authorisation rules: who may do what to which record | `app/Policies/CompanyPolicy.php` extends `OwnedRecordPolicy` |
| Form requests and API resources | Validation for incoming data, and the shape of the data sent to the browser | `app/Http/Requests/Crm/`, `app/Http/Resources/` |
| Inertia | Connects Laravel controllers to React pages: the controller picks a page and its props | `Inertia::render(...)` in `app/Http/Controllers/`, pages in `resources/js/pages/**` |
| Blade | The template language. Only the root HTML shell uses it here | `resources/views/app.blade.php` |
| Config and `.env` | `config/*.php` files read environment values with `env()` | `config/crm.php` reads `CRM_DEFAULT_ROLE` |
| Migrations, factories and seeders | Schema changes, fake data and initial data | `database/` |
| Artisan | The command-line tool (`php artisan …`) | `artisan`, `app/Console/Commands` |

Two rules that trip up newcomers:

- **Call `env()` only inside `config/` files.** In production, config is cached (`php artisan optimize`), and `env()` returns `null` everywhere else. In app code, use `config('crm.default_role')`.
- **Don't edit `vendor/`.** It holds the Composer packages and is rebuilt on every install. Change package behaviour through config, a service provider or a published file instead.

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
  ├─▶ Controller:     companies → CompanyController@index → Inertia::render('companies/index', …)
  ├─▶ Controller:     media/{media} → MediaDownloadController streams the file
  └─▶ Fortify:        GET /login → Inertia page auth/login, POST /login → Fortify's controller
  ▼
Your code ────────── authorise (policy) → query (Eloquent) → props (API resources) → Inertia::render
  ▼
Middleware ───────── HandleInertiaRequests adds shared props; the session is saved and cookies added
  ▼
Browser receives HTML (first visit) or a JSON page object (later visits)
```

Step by step:

1. **`public/index.php`** is the web root's front controller. It stops early if the app is in maintenance mode, loads Composer's autoloader and creates the application from `bootstrap/app.php`.
2. **`bootstrap/app.php`** loads `routes/web.php` and `routes/console.php`, and registers the `/up` health check used by Azure. It trusts the proxy headers from Azure's ingress, adds `HandleAppearance` and `HandleInertiaRequests` to the web middleware, and returns JSON errors for `api/*` and JSON requests.
3. **Service providers** in `bootstrap/providers.php` boot, and so do package providers, which Laravel discovers automatically:
   - `AppServiceProvider` lets admins pass every check (`Gate::before`), maps short type names for polymorphic columns (`enforceMorphMap`), blocks destructive database commands in production and sets the password rules.
   - `FortifyServiceProvider` connects Fortify to the auth pages in `resources/js/pages/auth/` (through `Inertia::render`) and to the actions in `app/Actions/Fortify/`, and sets the login rate limits.
4. **Middleware** runs. The web group handles cookies, the session and CSRF. Route middleware adds the checks on each route, for example `auth` and `verified` on the CRM routes and `can:users.manage` on `admin/users`.
5. **The router** runs one of two kinds of handler:
   - **Controller**, which covers every app page. Route parameters such as `{company}` are resolved into models automatically (route-model binding) and passed to the controller method. `MediaDownloadController` is a controller too: it checks the parent record's policy, then streams the file.
   - **Fortify**. The login, register and password-reset screens are Inertia pages rendered from `FortifyServiceProvider`. Their POST requests go to controllers inside the Fortify package.
6. **Your code** checks access with `Gate::authorize('view', $company)` (or a form request's `authorize()`), which calls the policy. It queries through Eloquent, wraps the records in API resources that add `can` flags, and returns `Inertia::render('companies/show', [...])`. Writes redirect instead, and flash a toast with `Inertia::flash('toast', [...])`.
7. **The response** goes back out through the middleware. `HandleInertiaRequests` adds the props every page shares: `auth.user`, `auth.can` (which nav items to show), `name` and `sidebarOpen`. On a first visit the browser gets complete HTML from `resources/views/app.blade.php`, with the page name and props embedded.

### After the page loads: Inertia visits

After the first response, React renders the page and Inertia takes over navigation.

- Inertia `<Link>` clicks and `router.get/post/delete(...)` calls are sent as XHR requests with an `X-Inertia` header. They go through the same routes, middleware and controllers, and the server answers with a JSON page object (component name and props) rather than HTML. React then swaps in the new page without a full reload.
- `resources/js/app.tsx` picks the layout from the page name: `auth/*` pages get `AuthLayout`, `settings/*` pages get `AppLayout` plus `SettingsLayout`, and everything else gets `AppLayout` (the sidebar). A page sets its breadcrumbs with `Page.layout = { breadcrumbs: [...] }`, or with a function of its props when the title comes from the record.
- Forms use Inertia's `<Form {...CompanyController.store.form()}>` or `useForm()`. Validation errors come back as props, and the controller runs the policy again on every write.
- Index pages keep search, filters and sorting in the query string (`hooks/use-index-filters.ts`). Each change calls `router.get(...)` with `preserveState` and `replace`, and the search box is debounced.
- The notes and attachments panels on the company and contact pages are ordinary props. The files they upload are posted as multipart form data.
- Toasts flashed by the server are shown by `sonner` (`hooks/use-flash-toast.ts`).

`CompanyController.store.form()` and `dashboard()` come from **Wayfinder**, which generates typed TypeScript helpers for each route and controller action into `resources/js/{actions,routes,wayfinder}`. The Vite plugin regenerates them in development when PHP is available. Otherwise run `php artisan wayfinder:generate --with-form`.

## Folder-by-folder tour

### Application code: `app/`

Everything in `app/` is autoloaded under the `App\` namespace (PSR-4), so `app/Models/Company.php` is `App\Models\Company`.

| Path | Purpose |
|---|---|
| `app/Actions/Attachments` | `StoreAttachment`: the upload validation rules, and moving an uploaded file into the media library |
| `app/Actions/Fortify` | How Fortify creates users (`CreateNewUser`) and resets passwords (`ResetUserPassword`) |
| `app/Concerns` | Traits that share validation rules for profiles and passwords |
| `app/Console/Commands` | Custom Artisan commands. `EnsureMediaContainer` is `php artisan media:ensure-container` |
| `app/Enums` | `Role` and `Permission`: the permission names, and which role gets which permission |
| `app/Http/Controllers` | One controller per area (`CompanyController`, `ContactController`, `NoteController`, `AttachmentController`, `DashboardController`, `Admin\UserController`, `Settings\*`), plus `MediaDownloadController` |
| `app/Http/Middleware` | `HandleInertiaRequests` (shared props) and `HandleAppearance` (reads the `appearance` cookie for light/dark mode) |
| `app/Http/Requests` | Form requests: validation rules and authorisation for a write. `Crm\CompanyRequest::attributesForSave()` also handles owner assignment and uppercases the country code |
| `app/Http/Resources` | API resources that decide which fields each page receives, including per-record `can` flags |
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
| `config/` | One file per subsystem (`database.php`, `session.php`, `filesystems.php`, …). Package configs such as `permission.php`, `media-library.php`, `activitylog.php`, `inertia.php` and `fortify.php` are published here so they can be changed. `crm.php` holds this app's own settings |
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
| `resources/js/app.tsx` | The Inertia entry point: finds pages, picks their layouts, sets the page title and mounts the toaster |
| `resources/js/pages/` | **React pages**, one per `Inertia::render()` name (for example `companies/index` → `pages/companies/index.tsx`). Props arrive as the component's arguments |
| `resources/js/pages/auth/` | Login, register, password reset and two-factor screens, rendered by Fortify |
| `resources/js/pages/settings/` | Profile, appearance and security (2FA, passkeys) pages |
| `resources/js/components/` | Shared React components: `notes-thread`, `attachments`, `confirm-dialog`, `pagination`, `sortable-head`, `text-field`, the sidebar and so on |
| `resources/js/components/ui/` | [shadcn/ui](https://ui.shadcn.com) components (Radix plus Tailwind), copied into the repo so they can be edited. `components.json` configures the shadcn CLI |
| `resources/js/layouts/` | Page shells: the sidebar app layout, the auth layout and the settings layout |
| `resources/js/hooks/`, `lib/`, `types/` | React hooks (appearance, flash toasts, index filters), helpers such as `cn()`, and TypeScript types for props |
| `resources/js/{actions,routes,wayfinder}/` | Generated by Wayfinder. Git-ignored; regenerate with `php artisan wayfinder:generate --with-form` |
| `resources/views/app.blade.php` | The one Blade view: the HTML shell that loads Vite and mounts the Inertia app |
| `resources/css/app.css` | Tailwind CSS 4 entry point, with the shadcn theme colours for light and dark mode |
| `vite.config.ts` | Builds the frontend with `laravel-vite-plugin`, the Inertia, React and Tailwind plugins, and the Wayfinder plugin. It also holds lint and format settings for `vp check` |
| `tsconfig.json` | TypeScript settings. `@/` maps to `resources/js/`. Check types with `npm run types:check` |
| `package.json`, `package-lock.json`, `.npmrc` | Node build dependencies. `node_modules/` is git-ignored |

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
| `tests/Feature/` | Tests that boot the whole app. They make HTTP requests (`$this->get(route(...))`, `$this->post(...)`) and check which page and props came back with `assertInertia(fn (Assert $page) => $page->component('companies/index')->has(...))`. Grouped by area: `Auth`, `Crm`, `Settings`, `Admin` |
| `tests/Unit/` | Tests for plain classes, without the framework booted |
| `tests/TestCase.php` | The base test class, with helpers such as `userWithRole()` |
| `phpunit.xml` | Test settings. Tests use in-memory SQLite, with `RefreshDatabase` giving each test a clean schema |
| `phpstan.neon` | Larastan (PHPStan for Laravel) at level 7 |
| `pint.json` | Code style rules for Laravel Pint |

### Operations and infrastructure

| Path | Purpose |
|---|---|
| `Dockerfile` | Multi-stage image: `base` (PHP-FPM + NGINX), `development` (source mounted from your machine), `vendor` (Composer install and Wayfinder generation), `assets` (Vite build, using the vendor stage's Wayfinder files), `production` (the final image) |
| `docker-compose.yml` | Local stack: `app`, `vite`, `mysql`, `azurite` (Blob Storage emulator) and `mailpit` (email catcher) |
| `docker/entrypoint.d/` | Development-only start-up scripts: `composer install`, Wayfinder generation (the `vite` service has no PHP), then migrations and the roles/permissions seeder |
| `docker/vite-dev.sh` | Start command for the `vite` service: wait for the app container's Composer install and Wayfinder files, then install, build and run the dev server |
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
| Add a page | Add a controller method that authorises and returns `Inertia::render('<area>/<name>', $props)`, add a route to `routes/web.php`, and create `resources/js/pages/<area>/<name>.tsx`. Run `php artisan wayfinder:generate --with-form` if Vite isn't doing it for you |
| Add a reusable interactive widget | A React component in `resources/js/components/`, given its data as props by the page |
| Add a UI primitive (dropdown, tabs, …) | Add the shadcn/ui component to `resources/js/components/ui/` (`npx shadcn@latest add <name>` uses `components.json`) |
| Change the database | `php artisan make:migration add_x_to_companies_table`, then `php artisan migrate` |
| Add a model | `php artisan make:model Deal -mf` (creates the model, migration and factory). Add it to `enforceMorphMap` in `AppServiceProvider` if it will be used in polymorphic relations |
| Control who can do something | Add a method to the model's policy and call `Gate::authorize('ability', $model)` in the controller. To show or hide a button, add the ability to the record's `can` flags in its API resource |
| Add a permission | Add it to `app/Enums/Permission.php` and `Role::permissions()`, then run `php artisan db:seed --class='Database\Seeders\ProductionSeeder'` |
| Add a setting | Add a key to `config/crm.php` that reads `env('…')`, and document it in `.env.example` |
| Validate a form | Put `rules()` and `authorize()` in a form request in `app/Http/Requests/`, type-hint it in the controller method, and show `errors.<field>` from Inertia's `<Form>` in the page |
| Ask before deleting something | Keep the record to delete in state and render one `ConfirmDialog` per page. Its button calls `router.delete(Controller.destroy.url(id))`, and the controller's `destroy()` authorises again. See `resources/js/pages/companies/index.tsx` |
| Keep filters in the URL | Use `useIndexFilters()` from `resources/js/hooks/use-index-filters.ts`, and read the same query parameters in the controller |
| Add a test | `tests/Feature/<Area>/<Thing>Test.php`, extending `Tests\TestCase` and using `RefreshDatabase` |

## Everyday Artisan commands

Prefix these with `docker compose exec app` when you use the Docker stack.

```bash
php artisan route:list --except-vendor   # every route and what handles it
php artisan make:controller DealController --resource
php artisan wayfinder:generate --with-form   # TypeScript route helpers for new routes
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
- [Inertia documentation](https://inertiajs.com)
- [React documentation](https://react.dev)
- [shadcn/ui components](https://ui.shadcn.com)
- [Laravel Wayfinder](https://github.com/laravel/wayfinder)
- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission), [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary), [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
