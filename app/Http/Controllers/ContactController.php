<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\MediaResource;
use App\Http\Resources\NoteResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    private const SORTABLE = ['last_name', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'mine' => $request->boolean('mine'),
            'sortBy' => in_array($request->input('sortBy'), self::SORTABLE, true) ? $request->input('sortBy') : 'last_name',
            'sortDirection' => $request->input('sortDirection') === 'desc' ? 'desc' : 'asc',
        ];

        $contacts = Contact::query()
            ->with(['company', 'owner'])
            ->search($filters['search'])
            ->when($filters['mine'], fn ($query) => $query->where('owner_id', $request->user()?->id))
            ->orderBy($filters['sortBy'], $filters['sortDirection'])
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('contacts/index', [
            'contacts' => ContactResource::collection($contacts),
            'filters' => $filters,
            'can' => [
                'create' => $request->user()?->can('create', Contact::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Contact::class);

        // "Add contact" on a company page links here with ?company={id}.
        $companyId = Company::query()->whereKey($request->integer('company'))->value('id');

        return $this->form($request, null, $companyId);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = new Contact(['owner_id' => $request->user()?->id]);
        $contact->fill($request->attributesForSave())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact saved.')]);

        return to_route('contacts.show', $contact);
    }

    public function show(Request $request, Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        $contact->load(['company', 'owner']);

        return Inertia::render('contacts/show', [
            'contact' => ContactResource::make($contact),
            // Closures, so the partial reload after adding a note only evaluates what it asks for.
            'notes' => fn () => NoteResource::collection($contact->notes()->with(['author', 'media'])->get()),
            'attachments' => fn () => MediaResource::collection($contact->getMedia('attachments')->sortByDesc('created_at')->values()),
            'maxUploadMb' => intdiv(config('crm.attachments.max_size_kb'), 1024),
            'can' => [
                'createNote' => $request->user()?->can('create', [Note::class, $contact]),
                'manageAttachments' => $request->user()?->can('update', $contact),
            ],
        ]);
    }

    public function edit(Request $request, Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return $this->form($request, $contact, $contact->company_id);
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->fill($request->attributesForSave())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact saved.')]);

        return to_route('contacts.show', $contact);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        // Back to the index with its filters and page intact.
        return back(fallback: route('contacts.index'));
    }

    private function form(Request $request, ?Contact $contact, mixed $companyId): Response
    {
        $canAssign = (bool) $request->user()?->can('assign', Contact::class);

        return Inertia::render('contacts/form', [
            'contact' => $contact ? ContactResource::make($contact) : null,
            'companyId' => $companyId,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'canAssign' => $canAssign,
            'users' => $canAssign ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
