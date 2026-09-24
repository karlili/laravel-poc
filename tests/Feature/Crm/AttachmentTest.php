<?php

namespace Tests\Feature\Crm;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    /**
     * A fake upload with real PDF bytes, so MIME sniffing sees a PDF.
     */
    private function fakePdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    public function test_owner_can_upload_documents_and_images(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->for($sales, 'owner')->create();

        Livewire::actingAs($sales)
            ->test('attachments', ['model' => $company])
            ->set('uploads', [
                UploadedFile::fake()->image('Site photo.png', 1600, 1200),
                $this->fakePdf('Contract.pdf'),
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('contract.pdf');

        $media = $company->fresh()->getMedia('attachments');

        $this->assertCount(2, $media);

        $image = $media->firstWhere('mime_type', 'image/png');
        $this->assertSame('site-photo.png', $image->file_name);
        $this->assertTrue($image->hasGeneratedConversion('thumb'));
        $this->assertTrue($image->hasGeneratedConversion('preview'));
        Storage::disk('media')->assertExists($image->getPathRelativeToRoot());
        Storage::disk('media')->assertExists($image->getPathRelativeToRoot('thumb'));
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->for($sales, 'owner')->create();

        Livewire::actingAs($sales)
            ->test('attachments', ['model' => $company])
            ->set('uploads', [UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload')])
            ->call('save')
            ->assertHasErrors('uploads.0');

        $this->assertCount(0, $company->fresh()->getMedia('attachments'));
    }

    public function test_users_who_cannot_edit_the_record_cannot_upload(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        Livewire::actingAs($sales)
            ->test('attachments', ['model' => $company])
            ->set('uploads', [UploadedFile::fake()->createWithContent('notes.txt', 'Plain text notes')])
            ->call('save')
            ->assertForbidden();
    }

    public function test_downloads_require_permission_to_view_the_record(): void
    {
        $company = Company::factory()->create();
        $media = $company->addMedia($this->fakePdf('Quote.pdf'))
            ->toMediaCollection('attachments');

        $this->actingAs($this->userWithRole(Role::Viewer))
            ->get(route('media.show', $media))
            ->assertOk()
            ->assertDownload('Quote.pdf');

        $this->actingAs(User::factory()->create())
            ->get(route('media.show', $media))
            ->assertForbidden();
    }

    public function test_note_attachments_follow_the_parent_record(): void
    {
        $sales = $this->userWithRole(Role::Sales);
        $company = Company::factory()->create();

        Livewire::actingAs($sales)
            ->test('notes-thread', ['notable' => $company])
            ->set('body', 'Signed copy attached.')
            ->set('uploads', [$this->fakePdf('signed.pdf')])
            ->call('add')
            ->assertHasNoErrors();

        $media = $company->notes()->first()->getFirstMedia('attachments');

        $this->assertNotNull($media);
        $this->actingAs($this->userWithRole(Role::Viewer))->get(route('media.show', $media))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('media.show', $media))->assertForbidden();
    }
}
