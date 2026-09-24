#!/usr/bin/env bash
# Creates the storage account that holds Terraform state. Run once per subscription.
#
#   az login
#   ./infra/scripts/bootstrap-state.sh <subscription-id> [location]
#
# Then put the printed storage account name into
# infra/terraform/environments/*.backend.hcl (or the TF_STATE_STORAGE_ACCOUNT
# GitHub variable).
set -euo pipefail

SUBSCRIPTION_ID="${1:?Usage: $0 <subscription-id> [location]}"
LOCATION="${2:-australiaeast}"
RESOURCE_GROUP="rg-crm-tfstate"
ACCOUNT="sttfcrm$(openssl rand -hex 3)"

az account set --subscription "$SUBSCRIPTION_ID"

az group create --name "$RESOURCE_GROUP" --location "$LOCATION" --output none

az storage account create \
  --name "$ACCOUNT" \
  --resource-group "$RESOURCE_GROUP" \
  --location "$LOCATION" \
  --sku Standard_ZRS \
  --kind StorageV2 \
  --min-tls-version TLS1_2 \
  --allow-blob-public-access false \
  --allow-shared-key-access false \
  --output none

az storage account blob-service-properties update \
  --account-name "$ACCOUNT" \
  --resource-group "$RESOURCE_GROUP" \
  --enable-versioning true \
  --enable-delete-retention true \
  --delete-retention-days 30 \
  --output none

# Lets the signed-in user read and write state with Entra ID.
az role assignment create \
  --assignee "$(az ad signed-in-user show --query id --output tsv)" \
  --role "Storage Blob Data Contributor" \
  --scope "$(az storage account show --name "$ACCOUNT" --resource-group "$RESOURCE_GROUP" --query id --output tsv)" \
  --output none

# Role assignments take a minute or two to apply.
for attempt in $(seq 1 12); do
  if az storage container create --name tfstate --account-name "$ACCOUNT" --auth-mode login --output none 2>/dev/null; then
    break
  fi
  [ "$attempt" -eq 12 ] && { echo "Could not create the tfstate container." >&2; exit 1; }
  echo "Waiting for the role assignment to apply..."
  sleep 10
done

echo "Terraform state storage account: $ACCOUNT"
