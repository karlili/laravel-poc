<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }

    /**
     * Admins may do everything; everyone else goes through policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole(Role::Admin->value) ? true : null);
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

        // Inertia props are resources; don't nest each one under "data".
        JsonResource::withoutWrapping();

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
