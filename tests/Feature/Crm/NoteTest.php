<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_add_a_note_to_any_visible_company(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        $this->actingAs($sales)
            ->from(route('companies.show', $company))
            ->post(route('companies.notes.store', $company), ['body' => 'Called about renewal.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.show', $company));

        $this->assertDatabaseHas('notes', [
            'notable_type' => 'company',
            'notable_id' => $company->id,
            'author_id' => $sales->id,
        ]);

        $this->get(route('companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.createNote', true)
                ->where('notes.0.body', 'Called about renewal.')
                ->where('notes.0.author.name', $sales->name)
                ->where('notes.0.can.delete', true),
            );
    }

    public function test_notes_can_be_added_to_contacts(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $contact = Contact::factory()->create();

        $this->actingAs($sales)
            ->post(route('contacts.notes.store', $contact), ['body' => 'Prefers email.'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notes', [
            'notable_type' => 'contact',
            'notable_id' => $contact->id,
            'body' => 'Prefers email.',
        ]);
    }

    public function test_a_note_needs_a_body(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->userWithRole(Role::Sales))
            ->post(route('companies.notes.store', $company), ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_viewers_cannot_add_notes(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        $company = Company::factory()->create();

        $this->actingAs($viewer)
            ->get(route('companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('can.createNote', false));

        $this->post(route('companies.notes.store', $company), ['body' => 'Sneaky'])->assertForbidden();

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_only_the_author_or_a_manager_can_delete_a_note(): void
    {
        $author = $this->userWithRole(Role::Sales);
        $otherSales = $this->userWithRole(Role::Sales);
        $manager = $this->userWithRole(Role::Manager);
        $company = Company::factory()->create();
        $first = Note::factory()->for($company, 'notable')->create(['author_id' => $author->id]);
        $second = Note::factory()->for($company, 'notable')->create(['author_id' => $author->id]);

        $this->actingAs($otherSales)
            ->get(route('companies.show', $company))
            ->assertInertia(fn (Assert $page) => $page->where('notes.0.can.delete', false));

        $this->delete(route('notes.destroy', $first))->assertForbidden();

        $this->actingAs($author)->delete(route('notes.destroy', $first))->assertRedirect();
        $this->actingAs($manager)->delete(route('notes.destroy', $second))->assertRedirect();

        $this->assertDatabaseCount('notes', 0);
    }
}
