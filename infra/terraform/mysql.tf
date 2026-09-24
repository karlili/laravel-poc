resource "azurerm_mysql_flexible_server" "main" {
  name                   = "mysql-${local.name}-${random_string.suffix.result}"
  resource_group_name    = azurerm_resource_group.main.name
  location               = azurerm_resource_group.main.location
  version                = var.mysql_version
  sku_name               = var.mysql_sku
  administrator_login    = "crmadmin"
  administrator_password = random_password.mysql.result

  delegated_subnet_id = azurerm_subnet.mysql.id
  private_dns_zone_id = azurerm_private_dns_zone.mysql.id

  backup_retention_days        = var.mysql_backup_retention_days
  geo_redundant_backup_enabled = var.mysql_geo_redundant_backup

  storage {
    size_gb           = var.mysql_storage_gb
    auto_grow_enabled = true
  }

  dynamic "high_availability" {
    for_each = var.mysql_high_availability ? [1] : []

    content {
      mode = "ZoneRedundant"
    }
  }

  tags = local.tags

  lifecycle {
    # Azure picks the zone when none is given; don't fight it on later plans.
    ignore_changes = [zone, high_availability[0].standby_availability_zone]
  }

  depends_on = [azurerm_private_dns_zone_virtual_network_link.mysql]
}

resource "azurerm_mysql_flexible_database" "crm" {
  name                = "crm"
  resource_group_name = azurerm_resource_group.main.name
  server_name         = azurerm_mysql_flexible_server.main.name
  charset             = "utf8mb4"
  collation           = "utf8mb4_unicode_ci"
}
