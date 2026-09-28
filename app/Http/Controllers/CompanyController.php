<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\CompanyRequest;
use App\Http\Resources\CompanyResource;
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

class CompanyController extends Controller
{
    private const SORTABLE = ['name', 'industry', 'created_at'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'industry' => $request->string('industry')->toString(),
            'mine' => $request->boolean('mine'),
            'sortBy' => in_array($request->input('sortBy'), self::SORTABLE, true) ? $request->input('sortBy') : 'name',
            'sortDirection' => $request->input('sortDirection') === 'desc' ? 'desc' : 'asc',
        ];

        $companies = Company::query()
            ->with('owner')
            ->withCount('contacts')
            ->search($filters['search'])
            ->when($filters['industry'] !== '', fn ($query) => $query->where('industry', $filters['industry']))
            ->when($filters['mine'], fn ($query) => $query->where('owner_id', $request->user()?->id))
            ->orderBy($filters['sortBy'], $filters['sortDirection'])
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('companies/index', [
            'companies' => CompanyResource::collection($companies),
            'filters' => $filters,
            'industries' => fn () => Company::query()->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry'),
            'can' => [
                'create' => $request->user()?->can('create', Company::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Company::class);

        return $this->form($request, null);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = new Company(['owner_id' => $request->user()?->id]);
        $company->fill($request->attributesForSave())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company saved.')]);

        return to_route('companies.show', $company);
    }

    public function show(Request $request, Company $company): Response
    {
        Gate::authorize('view', $company);

        $company->load(['owner', 'contacts' => fn ($query) => $query->orderBy('last_name')]);

        return Inertia::render('companies/show', [
            'company' => CompanyResource::make($company),
            // Closures, so the partial reload after adding a note only evaluates what it asks for.
            'notes' => fn () => NoteResource::collection($company->notes()->with(['author', 'media'])->get()),
            'attachments' => fn () => MediaResource::collection($company->getMedia('attachments')->sortByDesc('created_at')->values()),
            'maxUploadMb' => intdiv(config('crm.attachments.max_size_kb'), 1024),
            'can' => [
                'createContact' => $request->user()?->can('create', Contact::class),
                'createNote' => $request->user()?->can('create', [Note::class, $company]),
                'manageAttachments' => $request->user()?->can('update', $company),
            ],
        ]);
    }

    public function edit(Request $request, Company $company): Response
    {
        Gate::authorize('update', $company);

        return $this->form($request, $company);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $company->fill($request->attributesForSave())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company saved.')]);

        return to_route('companies.show', $company);
    }

    public function destroy(Company $company): RedirectResponse
    {
        Gate::authorize('delete', $company);

        $company->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Company deleted.')]);

        // Back to the index with its filters and page intact.
        return back(fallback: route('companies.index'));
    }

    private function form(Request $request, ?Company $company): Response
    {
        $canAssign = (bool) $request->user()?->can('assign', Company::class);

        return Inertia::render('companies/form', [
            'company' => $company ? CompanyResource::make($company) : null,
            'canAssign' => $canAssign,
            'users' => $canAssign ? User::query()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }
}
