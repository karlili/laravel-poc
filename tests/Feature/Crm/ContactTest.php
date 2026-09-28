<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewers_can_list_contacts_but_not_create(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        $contact = Contact::factory()->create(['first_name' => 'Jane', 'last_name' => 'Citizen']);

        $this->actingAs($viewer);

        $this->get(route('contacts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('contacts/index')
                ->where('contacts.data.0.full_name', 'Jane Citizen')
                ->where('can.create', false),
            );

        $this->get(route('contacts.show', $contact))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('contacts/show')
                ->where('contact.full_name', 'Jane Citizen')
                ->where('can.createNote', false),
            );

        $this->get(route('contacts.create'))->assertForbidden();
    }

    public function test_new_contact_form_prefills_the_company(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        $this->actingAs($sales)
            ->get(route('contacts.create', ['company' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('contacts/form')
                ->where('companyId', $company->id)
                ->where('companies.0.name', $company->name),
            );

        // An unknown company id is ignored rather than prefilled.
        $this->get(route('contacts.create', ['company' => 999]))
            ->assertInertia(fn (Assert $page) => $page->where('companyId', null));
    }

    public function test_sales_can_create_a_contact(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        $this->actingAs($sales)
            ->post(route('contacts.store'), [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.com',
                'company_id' => $company->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contacts.show', Contact::firstWhere('first_name', 'Ada')));

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Ada',
            'company_id' => $company->id,
            'owner_id' => $sales->id,
        ]);
    }

    public function test_contact_validation(): void
    {
        $this->actingAs($this->userWithRole(Role::Sales))
            ->post(route('contacts.store'), [
                'first_name' => '',
                'email' => 'not-an-email',
                'company_id' => 999,
            ])
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'company_id']);
    }

    public function test_sales_cannot_edit_contacts_owned_by_others(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $contact = Contact::factory()->create();

        $this->actingAs($sales);

        $this->get(route('contacts.edit', $contact))->assertForbidden();
        $this->put(route('contacts.update', $contact), ['first_name' => 'X', 'last_name' => 'Y'])->assertForbidden();
    }

    public function test_sales_can_delete_their_own_contact_but_not_others(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $own = Contact::factory()->for($sales, 'owner')->create();
        $theirs = Contact::factory()->create();

        $this->actingAs($sales)
            ->from(route('contacts.index'))
            ->delete(route('contacts.destroy', $own))
            ->assertRedirect(route('contacts.index'));

        $this->delete(route('contacts.destroy', $theirs))->assertForbidden();

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($theirs);
    }

    public function test_search_matches_company_name(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        Contact::factory()->for(Company::factory()->state(['name' => 'Wayne Enterprises']))->create(['last_name' => 'Wayne-Contact']);
        Contact::factory()->create(['last_name' => 'Unrelated']);

        $this->actingAs($viewer)
            ->get(route('contacts.index', ['search' => 'Wayne Enterprises']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.last_name', 'Wayne-Contact')
                ->where('contacts.data.0.company.name', 'Wayne Enterprises'),
            );
    }
}
