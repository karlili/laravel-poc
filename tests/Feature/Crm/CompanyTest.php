<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        $this->get(route('companies.index'))->assertOk()->assertSee('Acme Pty Ltd');
        $this->get(route('companies.show', $company))->assertOk();
        $this->get(route('companies.create'))->assertForbidden();
        $this->get(route('companies.edit', $company))->assertForbidden();
    }

    public function test_sales_can_create_a_company_they_own(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $other = User::factory()->create();

        Livewire::actingAs($sales)
            ->test('pages::companies.form')
            ->set('form.name', 'Globex')
            ->set('form.industry', 'Technology')
            ->set('form.country', 'au')
            ->set('form.owner_id', $other->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $company = Company::firstWhere('name', 'Globex');

        $this->assertNotNull($company);
        $this->assertSame('AU', $company->country);
        $this->assertTrue($company->isOwnedBy($sales), 'Sales users cannot assign records to others.');
    }

    public function test_company_name_is_required(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Sales))
            ->test('pages::companies.form')
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors(['form.name' => 'required']);
    }

    public function test_sales_can_only_edit_their_own_companies(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $own = Company::factory()->for($sales, 'owner')->create();
        $theirs = Company::factory()->create();

        $this->actingAs($sales);

        $this->get(route('companies.edit', $own))->assertOk();
        $this->get(route('companies.edit', $theirs))->assertForbidden();

        Livewire::test('pages::companies.form', ['company' => $own])
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Renamed', $own->fresh()->name);
    }

    public function test_managers_can_edit_and_reassign_any_company(): void
    {
        $manager = $this->userWithRole(Role::Manager);
        $newOwner = User::factory()->create();
        $company = Company::factory()->create();

        Livewire::actingAs($manager)
            ->test('pages::companies.form', ['company' => $company])
            ->set('form.owner_id', $newOwner->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($company->fresh()->isOwnedBy($newOwner));
    }

    public function test_sales_can_delete_their_own_company_but_not_others(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $own = Company::factory()->for($sales, 'owner')->create();
        $theirs = Company::factory()->create();

        Livewire::actingAs($sales)
            ->test('pages::companies.index')
            ->call('delete', $own->id)
            ->assertOk()
            ->call('delete', $theirs->id)
            ->assertForbidden();

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($theirs);
    }

    public function test_index_can_be_searched(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        Company::factory()->create(['name' => 'Initech']);
        Company::factory()->create(['name' => 'Umbrella Corp']);

        Livewire::actingAs($viewer)
            ->test('pages::companies.index')
            ->set('search', 'Initech')
            ->assertSee('Initech')
            ->assertDontSee('Umbrella Corp');
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
