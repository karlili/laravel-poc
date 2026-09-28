<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $canViewCompanies = (bool) $user?->can('viewAny', Company::class);
        $canViewContacts = (bool) $user?->can('viewAny', Contact::class);

        return Inertia::render('dashboard', [
            'stats' => [
                'companies' => $canViewCompanies ? Company::count() : 0,
                'contacts' => $canViewContacts ? Contact::count() : 0,
                'mine' => Company::where('owner_id', $user?->id)->count() + Contact::where('owner_id', $user?->id)->count(),
            ],
            // Notes span both record types, so only show them to users who can see both.
            'recentNotes' => $canViewCompanies && $canViewContacts
                ? Note::query()->with(['author', 'notable'])->latest()->limit(8)->get()->map(fn (Note $note) => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'created_at_diff' => $note->created_at?->diffForHumans(),
                    'notable' => $this->notableSummary($note),
                ])
                : [],
        ]);
    }

    /**
     * @return array{type: string, id: int, name: string}|null
     */
    private function notableSummary(Note $note): ?array
    {
        // Null when the record has since been archived (soft deleted).
        /** @var Company|Contact|null $notable */
        $notable = $note->notable;

        return match (true) {
            $notable instanceof Company => ['type' => 'company', 'id' => $notable->id, 'name' => $notable->name],
            $notable instanceof Contact => ['type' => 'contact', 'id' => $notable->id, 'name' => $notable->full_name],
            default => null,
        };
    }
}
