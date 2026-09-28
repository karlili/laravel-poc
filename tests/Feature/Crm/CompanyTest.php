<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('companies.index'))->assertRedirect(route('login'));
    }

    public function test_users_without_a_role_cannot_list_companies(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('companies.index'))
            ->assertForbidden();
    }

    public function test_viewers_can_list_and_view_but_not_create(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        $company = Company::factory()->create(['name' => 'Acme Pty Ltd']);

        $this->actingAs($viewer);

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/index')
                ->where('companies.data.0.name', 'Acme Pty Ltd')
                ->where('companies.data.0.can', ['update' => false, 'delete' => false])
                ->where('can.create', false),
            );

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/show')
                ->where('company.name', 'Acme Pty Ltd')
                ->where('can.manageAttachments', false),
            );

        $this->get(route('companies.create'))->assertForbidden();
        $this->get(route('companies.edit', $company))->assertForbidden();
        $this->post(route('companies.store'), ['name' => 'Nope'])->assertForbidden();
    }

    public function test_sales_can_create_a_company_they_own(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $other = User::factory()->create();

        $this->actingAs($sales)
            ->get(route('companies.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('companies/form')
                ->where('company', null)
                ->where('canAssign', false)
                ->where('users', []),
            );

        $response = $this->post(route('companies.store'), [
            'name' => 'Globex',
            'industry' => 'Technology',
            'country' => 'au',
            'owner_id' => $other->id,
        ]);

        $company = Company::firstWhere('name', 'Globex');

        $this->assertNotNull($company);
        $response->assertSessionHasNoErrors()->assertRedirect(route('companies.show', $company));
        $this->assertSame('AU', $company->country);
        $this->assertTrue($company->isOwnedBy($sales), 'Sales users cannot assign records to others.');
    }

    public function test_company_name_is_required(): void
    {
        $this->actingAs($this->userWithRole(Role::Sales))
            ->post(route('companies.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'The name field is required.']);

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_sales_can_only_edit_their_own_companies(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $own = Company::factory()->for($sales, 'owner')->create();
        $theirs = Company::factory()->create();

        $this->actingAs($sales);

        $this->get(route('companies.edit', $own))->assertOk();
        $this->get(route('companies.edit', $theirs))->assertForbidden();

        $this->put(route('companies.update', $own), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.show', $own));
        $this->put(route('companies.update', $theirs), ['name' => 'Hijacked'])->assertForbidden();

        $this->assertSame('Renamed', $own->fresh()->name);
        $this->assertNotSame('Hijacked', $theirs->fresh()->name);
    }

    public function test_managers_can_edit_and_reassign_any_company(): void
    {
        $manager = $this->userWithRole(Role::Manager);
        $newOwner = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($manager)
            ->get(route('companies.edit', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssign', true)
                ->has('users', User::count()),
            );

        $this->put(route('companies.update', $company), [
            'name' => $company->name,
            'owner_id' => $newOwner->id,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($company->fresh()->isOwnedBy($newOwner));
    }

    public function test_sales_can_delete_their_own_company_but_not_others(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $own = Company::factory()->for($sales, 'owner')->create();
        $theirs = Company::factory()->create();

        $this->actingAs($sales)
            ->from(route('companies.index', ['search' => $own->name]))
            ->delete(route('companies.destroy', $own))
            ->assertRedirect(route('companies.index', ['search' => $own->name]));

        $this->delete(route('companies.destroy', $theirs))->assertForbidden();

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($theirs);
    }

    public function test_index_can_be_searched_filtered_and_sorted(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        Company::factory()->create(['name' => 'Initech', 'industry' => 'Software']);
        Company::factory()->create(['name' => 'Umbrella Corp', 'industry' => 'Pharma']);
        Company::factory()->create(['name' => 'Aperture', 'industry' => 'Software']);

        $this->actingAs($viewer);

        $this->get(route('companies.index', ['search' => 'Initech']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.name', 'Initech')
                ->where('filters.search', 'Initech'),
            );

        $this->get(route('companies.index', ['industry' => 'Software', 'sortBy' => 'name', 'sortDirection' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 2)
                ->where('companies.data.0.name', 'Initech')
                ->where('companies.data.1.name', 'Aperture')
                ->where('industries', ['Pharma', 'Software']),
            );

        // Unknown sort columns fall back to the default instead of reaching the query.
        $this->get(route('companies.index', ['sortBy' => 'password']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.sortBy', 'name'));
    }

    public function test_only_mine_shows_records_the_user_owns(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        Company::factory()->for($sales, 'owner')->create(['name' => 'Mine']);
        Company::factory()->create(['name' => 'Not mine']);

        $this->actingAs($sales)
            ->get(route('companies.index', ['mine' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies.data', 1)
                ->where('companies.data.0.name', 'Mine')
                ->where('companies.data.0.can', ['update' => true, 'delete' => true]),
            );
    }

    public function test_changes_are_recorded_in_the_activity_log(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->for($sales, 'owner')->create();

        $this->actingAs($sales);
        $company->update(['name' => 'Audited Co']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'company',
            'subject_id' => $company->id,
            'event' => 'updated',
            'causer_id' => $sales->id,
        ]);
    }
}
