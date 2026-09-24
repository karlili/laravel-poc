# Created by infra/scripts/bootstrap-state.sh
resource_group_name  = "rg-crm-tfstate"
storage_account_name = "REPLACE_WITH_STATE_ACCOUNT"
container_name       = "tfstate"
key                  = "crm-dev.tfstate"
use_azuread_auth     = true
