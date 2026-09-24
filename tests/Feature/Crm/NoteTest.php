<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_add_a_note_to_any_visible_company(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        Livewire::actingAs($sales)
            ->test('notes-thread', ['notable' => $company])
            ->set('body', 'Called about renewal.')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSee('Called about renewal.');

        $this->assertDatabaseHas('notes', [
            'notable_type' => 'company',
            'notable_id' => $company->id,
            'author_id' => $sales->id,
        ]);
    }

    public function test_viewers_cannot_add_notes(): void
    {
        $viewer = $this->userWithRole(Role::Viewer);
        $company = Company::factory()->create();

        Livewire::actingAs($viewer)
            ->test('notes-thread', ['notable' => $company])
            ->assertDontSee('Add note')
            ->set('body', 'Sneaky')
            ->call('add')
            ->assertForbidden();

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

        Livewire::actingAs($otherSales)
            ->test('notes-thread', ['notable' => $company])
            ->call('delete', $first->id)
            ->assertForbidden();

        Livewire::actingAs($author)
            ->test('notes-thread', ['notable' => $company])
            ->call('delete', $first->id)
            ->assertOk();

        Livewire::actingAs($manager)
            ->test('notes-thread', ['notable' => $company])
            ->call('delete', $second->id)
            ->assertOk();

        $this->assertDatabaseCount('notes', 0);
    }
}
