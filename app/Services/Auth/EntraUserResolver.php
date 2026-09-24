<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as OAuthUser;
use SocialiteProviders\Manager\OAuth2\User as ProviderUser;

/**
 * Finds, links or creates the local user for a Microsoft Entra ID sign-in.
 */
class EntraUserResolver
{
    /**
     * @throws EntraSignInException
     */
    public function resolve(SocialiteUser $entraUser): User
    {
        $claims = $this->idTokenClaims($entraUser);
        $objectId = (string) ($claims['oid'] ?? $entraUser->getId());
        $tenantId = $claims['tid'] ?? null;

        $this->ensureExpectedTenant($tenantId);

        $email = $this->emailFor($entraUser);

        if ($objectId === '') {
            throw new EntraSignInException(__('Microsoft did not return an account identifier.'));
        }

        return DB::transaction(function () use ($objectId, $tenantId, $email, $entraUser) {
            $user = User::where('azure_oid', $objectId)->first();

            if ($user === null && $email !== null && config('crm.sso.link_by_email')) {
                $user = User::where('email', $email)->whereNull('azure_oid')->first();
            }

            if ($user === null) {
                if ($email === null) {
                    throw new EntraSignInException(__('Your Microsoft account has no email address.'));
                }

                if (User::where('email', $email)->exists()) {
                    throw new EntraSignInException(__('An account with this email already exists. Sign in with your password or ask an administrator to link it.'));
                }

                $user = new User(['name' => $entraUser->getName() ?: $email, 'email' => $email]);
                $user->save();
                $user->assignRole(config('crm.default_role'));
            }

            // An unverified local account with this email was never proven to
            // belong to this person, so drop its password when linking it.
            if (! $user->hasVerifiedEmail()) {
                $user->password = null;
            }

            $user->forceFill([
                'azure_oid' => $objectId,
                'azure_tenant_id' => $tenantId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return $user;
        });
    }

    /**
     * Reject tokens issued by any tenant other than the configured one.
     *
     * The app registration is single tenant, so Microsoft should only ever
     * issue tokens for that tenant; this is a defence-in-depth check.
     */
    private function ensureExpectedTenant(?string $tenantId): void
    {
        $expected = config('services.azure.tenant');

        if (! is_string($expected) || in_array(Str::lower($expected), ['', 'common', 'organizations', 'consumers'], true)) {
            return;
        }

        if ($tenantId === null || ! hash_equals(Str::lower($expected), Str::lower($tenantId))) {
            throw new EntraSignInException(__('Your Microsoft account belongs to an organisation that cannot use this CRM.'));
        }
    }

    private function emailFor(SocialiteUser $entraUser): ?string
    {
        $raw = $entraUser instanceof OAuthUser ? $entraUser->getRaw() : [];
        $email = $raw['mail'] ?? $entraUser->getEmail();

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? Str::lower($email) : null;
    }

    /**
     * Read the claims of the ID token returned alongside the access token.
     *
     * The token came straight from Microsoft's token endpoint over TLS in the
     * authorisation code exchange, so its signature is not re-verified here.
     *
     * @return array<string, mixed>
     */
    private function idTokenClaims(SocialiteUser $entraUser): array
    {
        $idToken = $entraUser instanceof ProviderUser ? ($entraUser->accessTokenResponseBody['id_token'] ?? null) : null;

        if (! is_string($idToken) || substr_count($idToken, '.') !== 2) {
            return [];
        }

        $payload = base64_decode(strtr(explode('.', $idToken)[1], '-_', '+/'), true);
        $claims = $payload === false ? null : json_decode($payload, true);

        return is_array($claims) ? $claims : [];
    }
}
