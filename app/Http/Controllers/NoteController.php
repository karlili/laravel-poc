<?php

namespace App\Http\Controllers;

use App\Actions\Attachments\StoreAttachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Notes on companies and contacts, each with optional attached files.
 */
class NoteController extends Controller
{
    public function storeForCompany(Request $request, Company $company, StoreAttachment $storeAttachment): RedirectResponse
    {
        return $this->store($request, $company, $storeAttachment);
    }

    public function storeForContact(Request $request, Contact $contact, StoreAttachment $storeAttachment): RedirectResponse
    {
        return $this->store($request, $contact, $storeAttachment);
    }

    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note deleted.')]);

        return back();
    }

    private function store(Request $request, Company|Contact $notable, StoreAttachment $storeAttachment): RedirectResponse
    {
        Gate::authorize('create', [Note::class, $notable]);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'uploads' => ['array', 'max:5'],
            'uploads.*' => StoreAttachment::rules(),
        ]);

        $note = new Note(['body' => $validated['body']]);
        $note->author()->associate($request->user());
        $notable->notes()->save($note);

        /** @var list<UploadedFile> $uploads */
        $uploads = $request->file('uploads', []);

        foreach ($uploads as $upload) {
            $storeAttachment($note, $upload);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Note added.')]);

        return back();
    }
}
