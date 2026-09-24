# subscription_id is passed with TF_VAR_subscription_id (see docs/deployment-azure.md).
environment = "prod"
location    = "australiaeast"

mysql_sku                   = "GP_Standard_D2ds_v4"
mysql_storage_gb            = 64
mysql_backup_retention_days = 35
mysql_geo_redundant_backup  = true
mysql_high_availability     = true

storage_replication         = "ZRS"
enable_defender_for_storage = true

web_min_replicas = 2
web_max_replicas = 6
worker_replicas  = 1

default_role      = "viewer"
entra_sso_enabled = false
# entra_client_id = "00000000-0000-0000-0000-000000000000"
# app_url         = "https://crm.example.com"
