variable "subscription_id" {
  description = "Azure subscription to deploy into."
  type        = string
}

variable "project" {
  description = "Short project name used in resource names (lowercase letters and digits)."
  type        = string
  default     = "crm"

  validation {
    condition     = can(regex("^[a-z][a-z0-9]{1,10}$", var.project))
    error_message = "Use 2-11 lowercase letters or digits, starting with a letter."
  }
}

variable "environment" {
  description = "Environment name, for example dev or prod."
  type        = string

  validation {
    condition     = can(regex("^[a-z][a-z0-9]{1,7}$", var.environment))
    error_message = "Use 2-8 lowercase letters or digits, starting with a letter."
  }
}

variable "location" {
  description = "Azure region."
  type        = string
  default     = "australiaeast"
}

variable "tags" {
  description = "Extra tags applied to every resource."
  type        = map(string)
  default     = {}
}

# --- Networking ------------------------------------------------------------

variable "vnet_address_space" {
  description = "Address space for the virtual network."
  type        = string
  default     = "10.20.0.0/16"
}

# --- Application -------------------------------------------------------------

variable "image_tag" {
  description = "Image tag used when Terraform creates the container apps. Later deploys update the image from CI, and Terraform ignores it."
  type        = string
  default     = "latest"
}

variable "app_url" {
  description = "Public URL of the app. Leave empty to use the Container Apps default domain."
  type        = string
  default     = ""
}

variable "default_role" {
  description = "Role given to new users: admin, manager, sales or viewer."
  type        = string
  default     = "viewer"
}

variable "web_min_replicas" {
  type    = number
  default = 1
}

variable "web_max_replicas" {
  type    = number
  default = 3
}

variable "worker_replicas" {
  type    = number
  default = 1
}

variable "web_cpu" {
  type    = number
  default = 0.5
}

variable "web_memory" {
  type    = string
  default = "1Gi"
}

# --- Microsoft Entra ID single sign-on --------------------------------------

variable "entra_sso_enabled" {
  description = "Show 'Sign in with Microsoft'. Needs entra_client_id and entra_client_secret."
  type        = bool
  default     = false
}

variable "entra_tenant_id" {
  description = "Tenant allowed to sign in. Defaults to the tenant Terraform runs in."
  type        = string
  default     = ""
}

variable "entra_client_id" {
  description = "Application (client) ID of the CRM app registration."
  type        = string
  default     = ""
}

variable "entra_client_secret" {
  description = "Client secret of the CRM app registration. Pass via TF_VAR_entra_client_secret."
  type        = string
  default     = ""
  sensitive   = true
}

# --- Database ----------------------------------------------------------------

variable "mysql_sku" {
  description = "MySQL Flexible Server SKU, for example B_Standard_B1ms (dev) or GP_Standard_D2ds_v4 (prod)."
  type        = string
  default     = "B_Standard_B1ms"
}

variable "mysql_version" {
  type    = string
  default = "8.4"
}

variable "mysql_storage_gb" {
  type    = number
  default = 32
}

variable "mysql_backup_retention_days" {
  type    = number
  default = 7
}

variable "mysql_geo_redundant_backup" {
  type    = bool
  default = false
}

variable "mysql_high_availability" {
  description = "Zone-redundant high availability. Needs a General Purpose or Business Critical SKU."
  type        = bool
  default     = false
}

# --- Storage -----------------------------------------------------------------

variable "storage_replication" {
  description = "Replication for the attachments storage account: LRS, ZRS, GRS or GZRS."
  type        = string
  default     = "LRS"
}

variable "enable_defender_for_storage" {
  description = "Enable Microsoft Defender for Storage (with malware scanning) on the attachments account."
  type        = bool
  default     = false
}

# --- Mail --------------------------------------------------------------------

variable "mail" {
  description = "SMTP settings for outgoing mail. The password is stored in Key Vault."
  type = object({
    mailer       = optional(string, "log")
    host         = optional(string, "")
    port         = optional(number, 587)
    username     = optional(string, "")
    from_address = optional(string, "crm@example.com")
  })
  default = {}
}

variable "mail_password" {
  type      = string
  default   = ""
  sensitive = true
}
