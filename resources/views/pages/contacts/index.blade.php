<?php

use App\Models\Company;
use App\Models\Contact;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Contacts')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[Url(except: 'last_name')]
    public string $sortBy = 'last_name';

    #[Url(except: 'asc')]
    public string $sortDirection = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', Contact::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'mine'], true)) {
            $this->resetPage();
        }
    }

    public function sort(string $column): void
    {
        if (! in_array($column, ['last_name', 'email', 'created_at'], true)) {
            return;
        }

        $this->sortDirection = $this->sortBy === $column && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
    }

    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    #[Computed]
    public function contacts(): LengthAwarePaginator
    {
        $sortBy = in_array($this->sortBy, ['last_name', 'email', 'created_at'], true) ? $this->sortBy : 'last_name';

        return Contact::query()
            ->with(['company', 'owner'])
            ->search($this->search)
            ->when($this->mine, fn ($query) => $query->where('owner_id', auth()->id()))
            ->orderBy($sortBy, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->orderBy('first_name')
            ->paginate(15);
    }

    public function delete(Contact $contact): void
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('Contact deleted.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Contacts') }}</flux:heading>

        @can('create', App\Models\Contact::class)
            <flux:button variant="primary" icon="plus" :href="route('contacts.create')" wire:navigate>
                {{ __('New contact') }}
            </flux:button>
        @endcan
    </div>

    <div class="flex flex-wrap items-end gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, email or company')" class="max-w-sm" />
        <flux:checkbox wire:model.live="mine" :label="__('Only mine')" />
    </div>

    <flux:table :paginate="$this->contacts">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'last_name'" :direction="$sortDirection" wire:click="sort('last_name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Company') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'email'" :direction="$sortDirection" wire:click="sort('email')">{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Owner') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Created') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->contacts as $contact)
                <flux:table.row :key="$contact->id">
                    <flux:table.cell variant="strong">
                        <flux:link :href="route('contacts.show', $contact)" wire:navigate>{{ $contact->full_name }}</flux:link>
                        @if ($contact->job_title)
                            <flux:text size="sm">{{ $contact->job_title }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($contact->company)
                            <flux:link :href="route('companies.show', $contact->company)" wire:navigate>{{ $contact->company->name }}</flux:link>
                        @else
                            —
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $contact->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $contact->owner?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $contact->created_at?->toFormattedDateString() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            @can('update', $contact)
                                <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('contacts.edit', $contact)" wire:navigate :aria-label="__('Edit')" />
                            @endcan
                            @can('delete', $contact)
                                <flux:modal.trigger :name="'delete-contact-'.$contact->id">
                                    <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Delete')" />
                                </flux:modal.trigger>

                                <flux:modal :name="'delete-contact-'.$contact->id" class="min-w-[22rem]">
                                    <div class="space-y-6 whitespace-normal">
                                        <flux:heading size="lg">{{ __('Delete :name?', ['name' => $contact->full_name]) }}</flux:heading>
                                        <flux:text>{{ __('The contact is moved to the archive.') }}</flux:text>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button variant="danger" wire:click="delete({{ $contact->id }})">{{ __('Delete') }}</flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No contacts found.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
