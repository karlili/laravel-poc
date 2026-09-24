<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use SocialiteProviders\Azure\User as AzureUser;
use Tests\TestCase;

class MicrosoftSignInTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT = '11111111-2222-3333-4444-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.azure.enabled' => true,
            'services.azure.client_id' => 'client-id',
            'services.azure.client_secret' => 'client-secret',
            'services.azure.redirect' => 'http://localhost/auth/microsoft/callback',
            'services.azure.tenant' => self::TENANT,
        ]);
    }

    /**
     * Make Socialite return the given Microsoft user from the callback.
     */
    private function fakeMicrosoftUser(string $oid, string $email, ?string $tenant = self::TENANT, string $name = 'Entra User'): void
    {
        $claims = rtrim(strtr(base64_encode((string) json_encode(['oid' => $oid, 'tid' => $tenant])), '+/', '-_'), '=');

        $user = (new AzureUser)
            ->setRaw(['id' => $oid, 'displayName' => $name, 'mail' => $email, 'userPrincipalName' => $email])
            ->map(['id' => $oid, 'name' => $name, 'email' => $email])
            ->setAccessTokenResponseBody(['id_token' => "header.{$claims}.signature"]);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($user);

        Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
    }

    public function test_login_page_shows_the_microsoft_button_when_enabled(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in with Microsoft');

        config(['services.azure.enabled' => false]);

        $this->get(route('login'))->assertOk()->assertDontSee('Sign in with Microsoft');
    }

    public function test_redirect_sends_the_user_to_their_tenant(): void
    {
        $response = $this->get(route('auth.microsoft.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://login.microsoftonline.com/'.self::TENANT.'/oauth2/v2.0/authorize', $response->headers->get('Location'));
    }

    public function test_first_sign_in_creates_a_verified_user_with_the_default_role(): void
    {
        $this->fakeMicrosoftUser('oid-new', 'New.Person@Contoso.com', name: 'New Person');

        $this->get(route('auth.microsoft.callback', ['code' => 'abc', 'state' => 'xyz']))
            ->assertRedirect(route('dashboard'));

        $user = User::firstWhere('email', 'new.person@contoso.com');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('New Person', $user->name);
        $this->assertSame('oid-new', $user->azure_oid);
        $this->assertSame(self::TENANT, $user->azure_tenant_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasRole(config('crm.default_role')));
    }

    public function test_returning_users_are_matched_by_object_id(): void
    {
        $user = $this->userWithRole(Role::Sales, ['azure_oid' => 'oid-existing', 'email' => 'old@contoso.com']);
        $this->fakeMicrosoftUser('oid-existing', 'renamed@contoso.com');

        $this->get(route('auth.microsoft.callback', ['code' => 'abc']));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_existing_local_account_is_linked_by_email(): void
    {
        $user = $this->userWithRole(Role::Manager, ['email' => 'jo@contoso.com']);
        $this->fakeMicrosoftUser('oid-jo', 'jo@contoso.com');

        $this->get(route('auth.microsoft.callback', ['code' => 'abc']));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('oid-jo', $user->fresh()->azure_oid);
        $this->assertTrue($user->fresh()->hasRole(Role::Manager->value));
    }

    public function test_linking_an_unverified_account_removes_its_password(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'squatter@contoso.com']);
        $this->fakeMicrosoftUser('oid-real', 'squatter@contoso.com');

        $this->get(route('auth.microsoft.callback', ['code' => 'abc']));

        $this->assertNull($user->fresh()->password);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tokens_from_another_tenant_are_rejected(): void
    {
        $this->fakeMicrosoftUser('oid-outsider', 'someone@fabrikam.com', tenant: '99999999-0000-0000-0000-000000000000');

        $this->get(route('auth.microsoft.callback', ['code' => 'abc']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_cancelled_sign_in_returns_to_login(): void
    {
        $this->get(route('auth.microsoft.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_linked_accounts_cannot_use_a_password(): void
    {
        $user = User::factory()->create(['azure_oid' => 'oid-linked']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_linked_accounts_can_use_a_password_when_sso_is_not_enforced(): void
    {
        config(['crm.sso.enforce_for_linked_users' => false]);
        $user = User::factory()->create(['azure_oid' => 'oid-linked']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }
}
