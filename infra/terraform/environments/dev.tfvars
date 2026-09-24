# subscription_id is passed with TF_VAR_subscription_id (see docs/deployment-azure.md).
environment = "dev"
location    = "australiaeast"

mysql_sku                   = "B_Standard_B1ms"
mysql_backup_retention_days = 7

web_min_replicas = 1
web_max_replicas = 2
worker_replicas  = 1

default_role      = "viewer"
entra_sso_enabled = false
# entra_client_id = "00000000-0000-0000-0000-000000000000"
