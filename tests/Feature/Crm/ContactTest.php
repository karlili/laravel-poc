<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewers_can_list_contacts_but_not_create(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        $contact = Contact::factory()->create(['first_name' => 'Jane', 'last_name' => 'Citizen']);

        $this->actingAs($viewer);

        $this->get(route('contacts.index'))->assertOk()->assertSee('Jane Citizen');
        $this->get(route('contacts.show', $contact))->assertOk();
        $this->get(route('contacts.create'))->assertForbidden();
    }

    public function test_new_contact_form_prefills_the_company(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        $this->actingAs($sales)
            ->get(route('contacts.create', ['company' => $company->id]))
            ->assertOk()
            ->assertSee($company->name);
    }

    public function test_sales_can_create_a_contact(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        Livewire::actingAs($sales)
            ->test('pages::contacts.form')
            ->set('form.first_name', 'Ada')
            ->set('form.last_name', 'Lovelace')
            ->set('form.email', 'ada@example.com')
            ->set('form.company_id', $company->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Ada',
            'company_id' => $company->id,
            'owner_id' => $sales->id,
        ]);
    }

    public function test_contact_validation(): void
    {
        Livewire::actingAs($this->userWithRole(Role::Sales))
            ->test('pages::contacts.form')
            ->set('form.first_name', '')
            ->set('form.email', 'not-an-email')
            ->set('form.company_id', 999)
            ->call('save')
            ->assertHasErrors(['form.first_name', 'form.last_name', 'form.email', 'form.company_id']);
    }

    public function test_sales_cannot_edit_contacts_owned_by_others(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $contact = Contact::factory()->create();

        $this->actingAs($sales)->get(route('contacts.edit', $contact))->assertForbidden();
    }

    public function test_search_matches_company_name(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        Contact::factory()->for(Company::factory()->state(['name' => 'Wayne Enterprises']))->create(['last_name' => 'Wayne-Contact']);
        Contact::factory()->create(['last_name' => 'Unrelated']);

        Livewire::actingAs($viewer)
            ->test('pages::contacts.index')
            ->set('search', 'Wayne Enterprises')
            ->assertSee('Wayne-Contact')
            ->assertDontSee('Unrelated');
    }
}
