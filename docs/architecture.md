# Architecture

## Runtime

```
                 ┌─────────────── Azure Container Apps environment (VNet) ───────────────┐
 Browser ──HTTPS──▶ web (PHP-FPM + NGINX, 1..n replicas)    migrate job (per deploy)     │
                 │        │            │                          │                      │
                 └────────┼────────────┼──────────────────────────┼──────────────────────┘
                          │            │ managed identity         │
               private    ▼            ▼                          ▼
               endpoint  MySQL      Blob Storage (media)      Key Vault (APP_KEY, DB password,
               (VNet)    Flexible   private container          SMTP password)
                         Server 8.4
```

- **One image, two processes.** The web app and the migration job run the same image with different start commands. It is built from the `production` target of the `Dockerfile`, on the `serversideup/php:8.4-fpm-nginx` base.
- **No queue worker or scheduler.** `QUEUE_CONNECTION=sync`, so anything queued runs inside the request, and there are no scheduled tasks.
- **Database-backed cache and sessions.** On Azure, `CACHE_STORE` and `SESSION_DRIVER` are both `database`, so MySQL is the only stateful dependency besides Blob Storage. Add Azure Managed Redis later if load calls for it. Local development uses the same database drivers.
- **Secrets** live in Key Vault. Container Apps reads them through the app's user-assigned managed identity, The only secrets stored in GitHub are the ones Terraform writes into Key Vault: the SMTP password.
- **Blob Storage has account keys disabled.** The app authenticates with its managed identity (`AZURE_STORAGE_CREDENTIAL=managed_identity`).

## Code layout

| Path | Purpose |
|---|---|
| `app/Models/{Company,Contact,Note}.php` | CRM models. They use soft deletes (companies and contacts), activity logging and the `HasAttachments` / `HasOwner` traits. |
| `app/Policies/` | `OwnedRecordPolicy` holds the shared rules. `CompanyPolicy` and `ContactPolicy` extend it. `NotePolicy` covers notes. |
| `app/Enums/{Role,Permission}.php` | Role and permission names, and which role gets which permission |
| `database/seeders/RolesAndPermissionsSeeder.php` | Syncs roles and permissions. It runs on every deploy through `ProductionSeeder`. |
| `app/Http/Controllers/` | `CompanyController` and `ContactController` (resource routes), `NoteController`, `AttachmentController`, `DashboardController`, `Admin\UserController` and `Settings\*`. Each action authorises through the policies, then returns `Inertia::render(...)` or a redirect with a flash toast. |
| `app/Http/Controllers/MediaDownloadController.php` | Serves attachments after checking the user can view the parent record |
| `app/Http/Requests/Crm/` | Validation for companies and contacts. `attributesForSave()` drops `owner_id` unless the user may reassign owners. |
| `app/Http/Resources/` | The props sent to React (`CompanyResource`, `ContactResource`, `NoteResource`, `MediaResource`, `UserSummaryResource`). Records carry `can` flags so the UI only shows allowed actions. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Props shared with every page: the signed-in user, navigation abilities (`auth.can`), the app name and the sidebar state |
| `app/Actions/Attachments/StoreAttachment.php` | Validation rules for attachments, and moving an uploaded file into media storage |
| `resources/js/pages/**` | React pages rendered by Inertia: companies, contacts, dashboard, admin, settings and auth |
| `resources/js/components/{notes-thread,attachments}.tsx` | Notes and attachments panels on the company and contact pages |
| `config/crm.php` | CRM settings: default role and attachment limits |

## Roles and permissions

| Role | Companies / contacts | Notes | Other |
|---|---|---|---|
| admin | everything | everything | manages users' roles and bypasses every check (`Gate::before`) |
| manager | view, create, edit and delete any record, and reassign owners | add, and delete any note | none |
| sales | view all; create; edit and delete **their own** records | add, and delete their own notes | none |
| viewer | view only | view only | none |

Ownership comes from `owner_id`, which defaults to the creator. Only users with `records.manage-any` (managers and admins) can reassign owners. Attachments follow their parent record: anyone who can view a record can download its files, and anyone who can edit it can upload or delete them.

New users get the role set by `CRM_DEFAULT_ROLE`, which defaults to `viewer`. An admin promotes them at **Administration → Users**.

## Authentication

Accounts are local and managed by Fortify: registration, email verification, password reset, two-factor authentication and passkeys. `FortifyServiceProvider` renders Fortify's screens as Inertia pages (`resources/js/pages/auth/*`). The security settings page talks to Fortify's two-factor endpoints and uses `@laravel/passkeys` for passkeys.

## Attachments

- Files go to the `attachments` media collection on Company, Contact and Note.
- The allowed types are PDF, Office documents, text/CSV and common images. The maximum size is `MEDIA_MAX_FILE_SIZE_MB`, 20 MB by default. Both the extension and the sniffed MIME type are checked.
- Images get `thumb` (300 px) and `preview` (1200 px) versions, which are generated during the upload request.
- Downloads go through `GET /media/{id}/{conversion?}`, which checks the parent record's policy and then streams the file.
- If you switch `MEDIA_DOWNLOAD_STRATEGY=redirect`, downloads use short-lived SAS URLs instead. That needs a storage credential that can sign them.
- Uploads are posted as multipart form data straight to `NoteController` and `AttachmentController`, which move each file into the media collection in the same request. Each request is handled by a single replica, so no shared temporary disk is needed. The image caps PHP uploads at 25 MB per file and 110 MB per request (`PHP_UPLOAD_MAX_FILE_SIZE`, `PHP_POST_MAX_SIZE` and `NGINX_CLIENT_MAX_BODY_SIZE` in the `Dockerfile`).
