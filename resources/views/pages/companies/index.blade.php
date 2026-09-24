<?php

use App\Models\Company;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Companies')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $industry = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[Url(except: 'name')]
    public string $sortBy = 'name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', Company::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'industry', 'mine'], true)) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['name', 'industry', 'created_at'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, ['name', 'industry', 'created_at'], true) ? $this->sortBy : 'name';

        return Company::query()
            ->with('owner')
            ->withCount('contacts')
            ->search($this->search)
            ->when($this->industry !== '', fn ($query) => $query->where('industry', $this->industry))
            ->when($this->mine, fn ($query) => $query->where('owner_id', auth()->id()))
            ->orderBy($sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(15);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function industries(): array
    {
        return Company::query()->whereNotNull('industry')->distinct()->orderBy('industry')->pluck('industry')->all();
    }

    public function delete(Company $company): void
    {
        $this->authorize('delete', $company);

        $company->delete();

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('Company deleted.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Companies') }}</flux:heading>

        @can('create', App\Models\Company::class)
            <flux:button variant="primary" icon="plus" :href="route('companies.create')" wire:navigate>
                {{ __('New company') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, domain or email')" class="max-w-sm" />

        <flux:select wire:model.live="industry" class="max-w-52">
            <flux:select.option value="">{{ __('All industries') }}</flux:select.option>
            @foreach ($this->industries as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:checkbox wire:model.live="mine" :label="__('Only mine')" />
    </div>

    <flux:table :paginate="$this->companies">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'industry'" :direction="$sortDirection" wire:click="sort('industry')">{{ __('Industry') }}</flux:table.column>
            <flux:table.column>{{ __('Contacts') }}</flux:table.column>
            <flux:table.column>{{ __('Owner') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Created') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->companies as $company)
                <flux:table.row :key="$company->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('companies.show', $company)" wire:navigate>{{ $company->name }}</flux:link>
                        @if ($company->domain)
                            <flux:text size="sm">{{ $company->domain }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $company->industry ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $company->contacts_count }}</flux:table.cell>
                    <flux:table.cell>{{ $company->owner?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $company->created_at?->toFormattedDateString() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @can('update', $company)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('companies.edit', $company)" wire:navigate :aria-label="__('Edit')" />
                            @endcan
                            @can('delete', $company)
                                <flux:modal.trigger :name="'delete-company-'.$company->id">
                                    <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                                </flux:modal.trigger>

                                <flux:modal :name="'delete-company-'.$company->id" class="min-w-[22rem]">
                                    <div class="space-y-6 whitespace-normal">
                                        <flux:heading size="lg">{{ __('Delete :name?', ['name' => $company->name]) }}</flux:heading>
                                        <flux:text>{{ __('The company is moved to the archive. Its contacts are kept.') }}</flux:text>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button variant="danger" wire:click="delete({{ $company->id }})">{{ __('Delete') }}</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No companies found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
