# Deploying to Azure

Everything runs in one resource group per environment, `rg-crm-<env>`:

| Resource | Purpose |
|---|---|
| Container Apps environment (in a VNet) | Runs the `web` app, the `worker` app, the `scheduler` job and the `migrate` job |
| Azure Container Registry (Basic) | Stores the app images. Pulls use the app's managed identity. |
| Azure Database for MySQL Flexible Server 8.4 | Private VNet access only, with TLS and automated backups |
| Storage account + private `media` container | Attachments. Account keys are off; access uses the managed identity. |
| Key Vault (RBAC) | `APP_KEY`, the database password, the Entra client secret and the SMTP password |
| Log Analytics workspace | Container logs |
| User-assigned managed identity | AcrPull, Storage Blob Data Contributor and Key Vault Secrets User |

The Terraform is in `infra/terraform`. It uses azurerm provider 5.x and Terraform 1.9 or later. Environment settings are in `environments/dev.tfvars` and `environments/prod.tfvars`.

## Prerequisites

- Azure CLI (`az`), Terraform 1.9 or later, and access to the subscription. To create the role assignments, you need **Owner**, or **Contributor** plus **Role Based Access Control Administrator**.
- An Entra app registration for SSO, if you want it (see [entra-setup.md](entra-setup.md)).

## 1. Create the Terraform state storage (once)

```bash
az login
./infra/scripts/bootstrap-state.sh <subscription-id> australiaeast
```

Put the printed storage account name in `infra/terraform/environments/dev.backend.hcl` and `prod.backend.hcl`, replacing `REPLACE_WITH_STATE_ACCOUNT`.

## 2. First deployment of an environment

The container apps need an image in the registry before they can start. Create the registry first, build the first image with ACR Tasks (no local Docker needed), then apply everything:

```bash
cd infra/terraform
export TF_VAR_subscription_id=<subscription-id>
export TF_VAR_entra_client_secret=<secret>        # only if SSO is enabled

terraform init -backend-config=environments/dev.backend.hcl
terraform apply -var-file=environments/dev.tfvars -target=azurerm_container_registry.main

az acr build --registry "$(terraform output -raw acr_name)" \
  --image crm:latest --target production ../..

terraform apply -var-file=environments/dev.tfvars
```

Then run the migrations and create the first admin:

```bash
RG=$(terraform output -raw resource_group_name)
az containerapp job start -g "$RG" -n "$(terraform output -raw migrate_job_name)"

# After signing up (or signing in with Microsoft) at the app URL, make yourself admin:
az containerapp exec -g "$RG" -n "$(terraform output -raw web_app_name)" --command \
  "php artisan tinker --execute=\"App\\Models\\User::where('email','you@example.com')->first()->syncRoles(['admin']);\""
```

`terraform output app_url` gives the site address. `terraform output entra_redirect_uri` gives the redirect URI to add to the Entra app registration.

## 3. Continuous deployment with GitHub Actions

The workflows sign in to Azure with **OpenID Connect**, so no Azure credentials are stored in GitHub.

### Create the deployment identity

```bash
APP_ID=$(az ad app create --display-name "crm-github-deployer" --query appId -o tsv)
az ad sp create --id "$APP_ID"

# One federated credential per GitHub environment (repeat for prod).
az ad app federated-credential create --id "$APP_ID" --parameters '{
  "name": "github-dev",
  "issuer": "https://token.actions.githubusercontent.com",
  "subject": "repo:<owner>/<repo>:environment:dev",
  "audiences": ["api://AzureADTokenExchange"]
}'

SUB=/subscriptions/<subscription-id>
az role assignment create --assignee "$APP_ID" --role "Contributor" --scope "$SUB"
az role assignment create --assignee "$APP_ID" --role "Role Based Access Control Administrator" --scope "$SUB"
az role assignment create --assignee "$APP_ID" --role "Storage Blob Data Contributor" \
  --scope "$(az storage account show -n <state-account> -g rg-crm-tfstate --query id -o tsv)"
```

The Role Based Access Control Administrator role lets Terraform create the app's role assignments. In production, limit it with a condition to the roles this stack assigns: AcrPull, Storage Blob Data Contributor, Key Vault Secrets User and Key Vault Secrets Officer.

### Configure the GitHub environments

Create the `dev` and `prod` environments under **Settings → Environments**. Add required reviewers to `prod`. Then set:

| Name | Type | Value |
|---|---|---|
| `AZURE_CLIENT_ID` | variable | `$APP_ID` above |
| `AZURE_TENANT_ID` | variable | tenant ID |
| `AZURE_SUBSCRIPTION_ID` | variable | subscription ID |
| `TF_STATE_STORAGE_ACCOUNT` | variable | state storage account name |
| `AZURE_RESOURCE_GROUP` | variable | `terraform output -raw resource_group_name` |
| `ACR_NAME` | variable | `terraform output -raw acr_name` |
| `IMAGE_REPOSITORY` | variable | `terraform output -raw image_repository` |
| `WEB_APP_NAME` | variable | `terraform output -raw web_app_name` |
| `WORKER_APP_NAME` | variable | `terraform output -raw worker_app_name` |
| `SCHEDULER_JOB_NAME` | variable | `terraform output -raw scheduler_job_name` |
| `MIGRATE_JOB_NAME` | variable | `terraform output -raw migrate_job_name` |
| `ENTRA_CLIENT_SECRET` | secret | Entra app client secret (optional) |
| `MAIL_PASSWORD` | secret | SMTP password (optional) |

### What the workflows do

| Workflow | Trigger | Steps |
|---|---|---|
| `ci.yml` | pull requests and pushes to `main` | Pint, Larastan, frontend build, PHPUnit against MySQL 8.4, and a Docker build |
| `terraform.yml` | changes under `infra/terraform`, or run manually | `fmt`, `validate` and `plan`. On `main` or a manual run, it also applies the saved plan, after environment approval if configured. |
| `deploy.yml` | pushes to `main` (dev), or run manually (dev/prod) | 1. Build and push `crm:<sha>`. 2. Run the `migrate` job with the new image and wait for it. 3. Update the web app, worker and scheduler. 4. Smoke-test `/up`. |

Terraform ignores the container image (`lifecycle.ignore_changes`), so infrastructure applies never roll back an app deploy.

## Operations

- **Logs:** Log Analytics → `ContainerAppConsoleLogs_CL`, or run `az containerapp logs show -g <rg> -n <app> --follow`.
- **Scaling:** `web_min_replicas`/`web_max_replicas` (HTTP concurrency rule) and `worker_replicas` in the tfvars.
- **Backups:** MySQL automated backups (`mysql_backup_retention_days`, geo-redundant in prod). Blob versioning and 30-day soft delete protect attachments.
- **Malware scanning:** set `enable_defender_for_storage = true` (on in prod) for Defender for Storage on-upload scanning.
- **Custom domain:** add it to the web container app (managed certificate), then set `app_url` and update the Entra redirect URI.

## Hardening follow-ups

- The app connects to MySQL as the server admin. Create a dedicated database user, or use Microsoft Entra authentication for MySQL.
- Put Azure Front Door with WAF in front of the web app, and restrict the Container Apps ingress to it.
- Put Key Vault and Storage behind private endpoints. They currently use public endpoints with RBAC and managed identity.
