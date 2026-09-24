output "resource_group_name" {
  value = azurerm_resource_group.main.name
}

output "app_url" {
  value = local.app_url
}

output "acr_name" {
  value = azurerm_container_registry.main.name
}

output "acr_login_server" {
  value = azurerm_container_registry.main.login_server
}

output "image_repository" {
  value = "${azurerm_container_registry.main.login_server}/${var.project}"
}

output "web_app_name" {
  value = azurerm_container_app.web.name
}

output "worker_app_name" {
  value = azurerm_container_app.worker.name
}

output "scheduler_job_name" {
  value = azurerm_container_app_job.scheduler.name
}

output "migrate_job_name" {
  value = azurerm_container_app_job.migrate.name
}

output "entra_redirect_uri" {
  description = "Add this redirect URI to the Entra app registration."
  value       = "${local.app_url}/auth/microsoft/callback"
}

output "mysql_fqdn" {
  value = azurerm_mysql_flexible_server.main.fqdn
}

output "key_vault_name" {
  value = azurerm_key_vault.main.name
}

output "storage_account_name" {
  value = azurerm_storage_account.media.name
}
