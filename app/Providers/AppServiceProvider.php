<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureSocialite();
    }

    /**
     * Admins may do everything; everyone else goes through policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole(Role::Admin->value) ? true : null);
    }

    /**
     * Register the Microsoft Entra ID (Azure) Socialite driver.
     */
    protected function configureSocialite(): void
    {
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', AzureProvider::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Store short, stable type names instead of class names in polymorphic
        // columns (notes, media, activity log, roles).
        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'contact' => Contact::class,
            'note' => Note::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
