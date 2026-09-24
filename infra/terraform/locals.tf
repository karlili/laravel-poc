locals {
  name = "${var.project}-${var.environment}"

  # Storage accounts, registries and Key Vaults only allow short alphanumeric names.
  compact_name = "${var.project}${var.environment}${random_string.suffix.result}"

  web_app_name = "ca-${local.name}-web"
  app_url      = var.app_url != "" ? var.app_url : "https://${local.web_app_name}.${azurerm_container_app_environment.main.default_domain}"
  image        = "${azurerm_container_registry.main.login_server}/${var.project}:${var.image_tag}"

  entra_tenant_id = var.entra_tenant_id != "" ? var.entra_tenant_id : data.azurerm_client_config.current.tenant_id

  tags = merge({
    project     = var.project
    environment = var.environment
    managed-by  = "terraform"
  }, var.tags)
}

resource "random_string" "suffix" {
  length  = 4
  upper   = false
  special = false
}
