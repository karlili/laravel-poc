<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\StoreAttachment;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Files attached directly to a company or contact. Managing them needs
 * update access to the record.
 */
class AttachmentController extends Controller
{
    public function storeForCompany(Request $request, Company $company, StoreAttachment $storeAttachment): RedirectResponse
    {
        return $this->store($request, $company, $storeAttachment);
    }

    public function storeForContact(Request $request, Contact $contact, StoreAttachment $storeAttachment): RedirectResponse
    {
        return $this->store($request, $contact, $storeAttachment);
    }

    public function destroy(Media $media): RedirectResponse
    {
        $record = $media->model;

        // Only record attachments are managed here; a note's files go with the note.
        abort_unless(($record instanceof Company || $record instanceof Contact) && $media->collection_name === 'attachments', 404);

        Gate::authorize('update', $record);

        $media->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }

    private function store(Request $request, Company|Contact $record, StoreAttachment $storeAttachment): RedirectResponse
    {
        Gate::authorize('update', $record);

        $request->validate([
            'uploads' => ['required', 'array', 'max:10'],
            'uploads.*' => StoreAttachment::rules(),
        ]);

        /** @var list<UploadedFile> $uploads */
        $uploads = $request->file('uploads', []);

        foreach ($uploads as $upload) {
            $storeAttachment($record, $upload);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Files uploaded.')]);

        return back();
    }
}
