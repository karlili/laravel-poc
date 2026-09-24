resource "azurerm_container_app_environment" "main" {
  name                       = "cae-${local.name}"
  resource_group_name        = azurerm_resource_group.main.name
  location                   = azurerm_resource_group.main.location
  log_analytics_workspace_id = azurerm_log_analytics_workspace.main.id
  infrastructure_subnet_id   = azurerm_subnet.apps.id
  tags                       = local.tags

  workload_profile {
    name                  = "Consumption"
    workload_profile_type = "Consumption"
  }
}

locals {
  # Settings shared by the web app, the queue worker and the jobs.
  app_env = {
    APP_NAME  = "Laravel CRM"
    APP_ENV   = "production"
    APP_DEBUG = "false"
    APP_URL   = local.app_url
    LOG_LEVEL = "info"

    DB_CONNECTION     = "mysql"
    DB_HOST           = azurerm_mysql_flexible_server.main.fqdn
    DB_PORT           = "3306"
    DB_DATABASE       = azurerm_mysql_flexible_database.crm.name
    DB_USERNAME       = azurerm_mysql_flexible_server.main.administrator_login
    MYSQL_ATTR_SSL_CA = "/etc/ssl/certs/ca-certificates.crt"

    SESSION_DRIVER        = "database"
    SESSION_SECURE_COOKIE = "true"
    CACHE_STORE           = "database"
    QUEUE_CONNECTION      = "database"

    MEDIA_DISK                          = "azure"
    MEDIA_DOWNLOAD_STRATEGY             = "stream"
    LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK = "azure"
    AZURE_STORAGE_ACCOUNT_NAME          = azurerm_storage_account.media.name
    AZURE_STORAGE_CONTAINER             = azurerm_storage_container.media.name
    AZURE_STORAGE_CREDENTIAL            = "managed_identity"
    AZURE_STORAGE_CLIENT_ID             = azurerm_user_assigned_identity.app.client_id

    CRM_DEFAULT_ROLE       = var.default_role
    AUTH_MICROSOFT_ENABLED = tostring(var.entra_sso_enabled)
    ENTRA_TENANT_ID        = local.entra_tenant_id
    ENTRA_CLIENT_ID        = var.entra_client_id
    ENTRA_REDIRECT_URI     = "${local.app_url}/auth/microsoft/callback"

    MAIL_MAILER       = var.mail.mailer
    MAIL_HOST         = var.mail.host
    MAIL_PORT         = tostring(var.mail.port)
    MAIL_USERNAME     = var.mail.username
    MAIL_FROM_ADDRESS = var.mail.from_address
  }

  # Environment variable => Key Vault secret name.
  app_secret_env = {
    APP_KEY             = "app-key"
    DB_PASSWORD         = "db-password"
    ENTRA_CLIENT_SECRET = "entra-client-secret"
    MAIL_PASSWORD       = "mail-password"
  }

  artisan = ["php", "/var/www/html/artisan"]
}

resource "azurerm_container_app" "web" {
  name                         = local.web_app_name
  resource_group_name          = azurerm_resource_group.main.name
  container_app_environment_id = azurerm_container_app_environment.main.id
  revision_mode                = "Single"
  workload_profile_name        = "Consumption"
  tags                         = local.tags

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  registry {
    server   = azurerm_container_registry.main.login_server
    identity = azurerm_user_assigned_identity.app.id
  }

  dynamic "secret" {
    for_each = azurerm_key_vault_secret.app

    content {
      name                = secret.key
      key_vault_secret_id = secret.value.versionless_id
      identity            = azurerm_user_assigned_identity.app.id
    }
  }

  ingress {
    external_enabled = true
    target_port      = 8080
    transport        = "auto"

    traffic_weight {
      latest_revision = true
      percentage      = 100
    }
  }

  template {
    min_replicas = var.web_min_replicas
    max_replicas = var.web_max_replicas

    container {
      name   = "web"
      image  = local.image
      cpu    = var.web_cpu
      memory = var.web_memory

      dynamic "env" {
        for_each = local.app_env

        content {
          name  = env.key
          value = env.value
        }
      }

      dynamic "env" {
        for_each = local.app_secret_env

        content {
          name        = env.key
          secret_name = env.value
        }
      }

      startup_probe {
        transport               = "HTTP"
        port                    = 8080
        path                    = "/up"
        failure_count_threshold = 10
        interval_seconds        = 5
      }

      liveness_probe {
        transport = "HTTP"
        port      = 8080
        path      = "/up"
      }

      readiness_probe {
        transport = "HTTP"
        port      = 8080
        path      = "/up"
      }
    }

    http_scale_rule {
      name                = "http"
      concurrent_requests = "50"
    }
  }

  lifecycle {
    # CI deploys new images with `az containerapp update`.
    ignore_changes = [template[0].container[0].image]
  }

  depends_on = [
    azurerm_role_assignment.app_acr_pull,
    azurerm_role_assignment.app_kv_secrets_user,
    azurerm_role_assignment.app_blob_contributor,
  ]
}

resource "azurerm_container_app" "worker" {
  name                         = "ca-${local.name}-worker"
  resource_group_name          = azurerm_resource_group.main.name
  container_app_environment_id = azurerm_container_app_environment.main.id
  revision_mode                = "Single"
  workload_profile_name        = "Consumption"
  tags                         = local.tags

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  registry {
    server   = azurerm_container_registry.main.login_server
    identity = azurerm_user_assigned_identity.app.id
  }

  dynamic "secret" {
    for_each = azurerm_key_vault_secret.app

    content {
      name                = secret.key
      key_vault_secret_id = secret.value.versionless_id
      identity            = azurerm_user_assigned_identity.app.id
    }
  }

  template {
    min_replicas = var.worker_replicas
    max_replicas = var.worker_replicas

    container {
      name    = "worker"
      image   = local.image
      cpu     = 0.5
      memory  = "1Gi"
      command = concat(local.artisan, ["queue:work", "--tries=3", "--max-time=3600"])

      dynamic "env" {
        for_each = local.app_env

        content {
          name  = env.key
          value = env.value
        }
      }

      dynamic "env" {
        for_each = local.app_secret_env

        content {
          name        = env.key
          secret_name = env.value
        }
      }
    }
  }

  lifecycle {
    ignore_changes = [template[0].container[0].image]
  }

  depends_on = [
    azurerm_role_assignment.app_acr_pull,
    azurerm_role_assignment.app_kv_secrets_user,
    azurerm_role_assignment.app_blob_contributor,
  ]
}

# Runs Laravel's scheduler every minute.
resource "azurerm_container_app_job" "scheduler" {
  name                         = "caj-${local.name}-scheduler"
  resource_group_name          = azurerm_resource_group.main.name
  location                     = azurerm_resource_group.main.location
  container_app_environment_id = azurerm_container_app_environment.main.id
  workload_profile_name        = "Consumption"
  replica_timeout_in_seconds   = 600
  replica_retry_limit          = 0
  tags                         = local.tags

  schedule_trigger_config {
    cron_expression          = "* * * * *"
    parallelism              = 1
    replica_completion_count = 1
  }

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  registry {
    server   = azurerm_container_registry.main.login_server
    identity = azurerm_user_assigned_identity.app.id
  }

  dynamic "secret" {
    for_each = azurerm_key_vault_secret.app

    content {
      name                = secret.key
      key_vault_secret_id = secret.value.versionless_id
      identity            = azurerm_user_assigned_identity.app.id
    }
  }

  template {
    container {
      name    = "scheduler"
      image   = local.image
      cpu     = 0.25
      memory  = "0.5Gi"
      command = concat(local.artisan, ["schedule:run"])

      dynamic "env" {
        for_each = local.app_env

        content {
          name  = env.key
          value = env.value
        }
      }

      dynamic "env" {
        for_each = local.app_secret_env

        content {
          name        = env.key
          secret_name = env.value
        }
      }
    }
  }

  lifecycle {
    ignore_changes = [template[0].container[0].image]
  }

  depends_on = [
    azurerm_role_assignment.app_acr_pull,
    azurerm_role_assignment.app_kv_secrets_user,
  ]
}

# Runs migrations and seeds roles. Started by CI before each deploy.
resource "azurerm_container_app_job" "migrate" {
  name                         = "caj-${local.name}-migrate"
  resource_group_name          = azurerm_resource_group.main.name
  location                     = azurerm_resource_group.main.location
  container_app_environment_id = azurerm_container_app_environment.main.id
  workload_profile_name        = "Consumption"
  replica_timeout_in_seconds   = 900
  replica_retry_limit          = 0
  tags                         = local.tags

  manual_trigger_config {
    parallelism              = 1
    replica_completion_count = 1
  }

  identity {
    type         = "UserAssigned"
    identity_ids = [azurerm_user_assigned_identity.app.id]
  }

  registry {
    server   = azurerm_container_registry.main.login_server
    identity = azurerm_user_assigned_identity.app.id
  }

  dynamic "secret" {
    for_each = azurerm_key_vault_secret.app

    content {
      name                = secret.key
      key_vault_secret_id = secret.value.versionless_id
      identity            = azurerm_user_assigned_identity.app.id
    }
  }

  template {
    container {
      name    = "migrate"
      image   = local.image
      cpu     = 0.5
      memory  = "1Gi"
      command = concat(local.artisan, ["migrate", "--force", "--seed", "--seeder=Database\\Seeders\\ProductionSeeder"])

      dynamic "env" {
        for_each = local.app_env

        content {
          name  = env.key
          value = env.value
        }
      }

      dynamic "env" {
        for_each = local.app_secret_env

        content {
          name        = env.key
          secret_name = env.value
        }
      }
    }
  }

  lifecycle {
    ignore_changes = [template[0].container[0].image]
  }

  depends_on = [
    azurerm_role_assignment.app_acr_pull,
    azurerm_role_assignment.app_kv_secrets_user,
  ]
}
