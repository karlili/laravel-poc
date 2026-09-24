terraform {
  required_version = ">= 1.9"

  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 5.6"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.9"
    }
  }

  # State lives in Azure Storage. Settings come from environments/<env>.backend.hcl:
  #   terraform init -backend-config=environments/dev.backend.hcl
  backend "azurerm" {}
}

provider "azurerm" {
  features {
    key_vault {
      purge_soft_delete_on_destroy = false
    }
  }

  subscription_id = var.subscription_id

  # Use Entra ID (not account keys) for storage data-plane calls.
  storage_use_azuread = true
}

data "azurerm_client_config" "current" {}
