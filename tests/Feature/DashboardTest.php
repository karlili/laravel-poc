<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_users_without_a_role_see_no_records(): void
    {
        Company::factory()->create();
        Note::factory()->for(Company::factory(), 'notable')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats', ['companies' => 0, 'contacts' => 0, 'mine' => 0])
                ->where('recentNotes', [])
                ->where('auth.can', ['viewCompanies' => false, 'viewContacts' => false, 'manageUsers' => false]),
            );
    }

    public function test_dashboard_shows_counts_and_recent_notes(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->for($sales, 'owner')->create(['name' => 'Acme']);
        Contact::factory()->count(2)->create();
        Note::factory()->for($company, 'notable')->create(['author_id' => $sales->id, 'body' => 'Kick-off call']);

        $this->actingAs($sales)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.companies', Company::count())
                ->where('stats.contacts', 2)
                ->where('stats.mine', 1)
                ->where('recentNotes.0.body', 'Kick-off call')
                ->where('recentNotes.0.author', $sales->name)
                ->where('recentNotes.0.notable', ['type' => 'company', 'id' => $company->id, 'name' => 'Acme']),
            );
    }
}
