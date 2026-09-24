# Microsoft Entra ID single sign-on

The CRM lets people **Sign in with Microsoft** using accounts from one Entra ID tenant (single tenant).

## 1. Register the application

1. In the [Microsoft Entra admin centre](https://entra.microsoft.com), go to **Identity → Applications → App registrations → New registration**.
2. Fill in the registration:
   - **Name:** `Laravel CRM` (or your environment's name, for example `Laravel CRM (dev)`).
   - **Supported account types:** *Accounts in this organizational directory only (single tenant)*.
   - **Redirect URI:** platform **Web**, with `http://localhost:8000/auth/microsoft/callback` for local development.
3. After creating it, open **Authentication** and add a redirect URI for each deployed environment. Terraform prints it as the `entra_redirect_uri` output:
   ```
   https://<web-app>.<environment-domain>/auth/microsoft/callback
   ```
4. Under **Certificates & secrets → Client secrets**, create a secret and copy its **Value**.
5. **API permissions:** keep the default Microsoft Graph delegated permission `User.Read`. The app also requests `openid`, `profile` and `email`, which need no admin consent.
6. *(Optional)* To limit who can sign in, open **Enterprise applications → Laravel CRM → Properties**, set **Assignment required?** to **Yes**, and assign users or groups under **Users and groups**.

## 2. Configure the app

Local `.env`:

```dotenv
AUTH_MICROSOFT_ENABLED=true
ENTRA_TENANT_ID=<Directory (tenant) ID>
ENTRA_CLIENT_ID=<Application (client) ID>
ENTRA_CLIENT_SECRET=<client secret value>
ENTRA_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

On Azure, set these in Terraform (`environments/<env>.tfvars`) instead:

```hcl
entra_sso_enabled = true
entra_client_id   = "<Application (client) ID>"
# entra_tenant_id defaults to the tenant Terraform runs in.
```

Pass the secret as the `ENTRA_CLIENT_SECRET` GitHub environment secret, which becomes `TF_VAR_entra_client_secret`. Terraform stores it in Key Vault.

## How accounts are matched

| Situation | Result |
|---|---|
| Entra object ID already linked to a user | That user is signed in |
| No link, but a local account has the same email (`SSO_LINK_BY_EMAIL=true`) | The account is linked. If its email was unverified, its password is removed. |
| No matching account | A new user is created with the verified email, no password and the `CRM_DEFAULT_ROLE` role |
| Token from a different tenant | Sign-in is refused |

With `SSO_ENFORCE_FOR_LINKED_USERS=true` (the default), linked accounts can no longer use a local password or reset one. Disabling a user in Entra ID therefore removes their access to the CRM.

## Troubleshooting

- **AADSTS50011 (redirect URI mismatch):** the URI in the app registration must exactly match `ENTRA_REDIRECT_URI`, including `http`/`https` and any trailing path.
- **The redirect URI shows `http://` on Azure:** the app trusts the Container Apps ingress proxy headers (`bootstrap/app.php`). Check that `APP_URL` starts with `https://`.
- **"belongs to an organisation that cannot use this CRM":** the user signed in with an account from another tenant.
