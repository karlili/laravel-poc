resource "azurerm_key_vault" "main" {
  name                       = "kv-${local.compact_name}"
  resource_group_name        = azurerm_resource_group.main.name
  location                   = azurerm_resource_group.main.location
  tenant_id                  = data.azurerm_client_config.current.tenant_id
  sku_name                   = "standard"
  rbac_authorization_enabled = true
  purge_protection_enabled   = var.environment == "prod"
  soft_delete_retention_days = 30
  tags                       = local.tags
}

# Lets the identity running Terraform (a person or the CI service principal) write secrets.
resource "azurerm_role_assignment" "deployer_kv_secrets_officer" {
  scope                = azurerm_key_vault.main.id
  role_definition_name = "Key Vault Secrets Officer"
  principal_id         = data.azurerm_client_config.current.object_id
}

resource "azurerm_role_assignment" "app_kv_secrets_user" {
  scope                = azurerm_key_vault.main.id
  role_definition_name = "Key Vault Secrets User"
  principal_id         = azurerm_user_assigned_identity.app.principal_id
  principal_type       = "ServicePrincipal"
}

resource "random_bytes" "app_key" {
  length = 32
}

resource "random_password" "mysql" {
  length      = 32
  special     = false
  min_lower   = 4
  min_upper   = 4
  min_numeric = 4
}

locals {
  secrets = {
    app-key             = "base64:${random_bytes.app_key.base64}"
    db-password         = random_password.mysql.result
    entra-client-secret = var.entra_client_secret != "" ? var.entra_client_secret : "not-set"
    mail-password       = var.mail_password != "" ? var.mail_password : "not-set"
  }
}

resource "azurerm_key_vault_secret" "app" {
  for_each = local.secrets

  name         = each.key
  value        = each.value
  key_vault_id = azurerm_key_vault.main.id

  depends_on = [azurerm_role_assignment.deployer_kv_secrets_officer]
}
