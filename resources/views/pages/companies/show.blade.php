<?php

use App\Models\Company;
use Livewire\Component;

new class extends Component {
    public Company $company;

    public function mount(Company $company): void
    {
        $this->authorize('view', $company);

        $this->company = $company->load(['owner', 'contacts' => fn ($query) => $query->orderBy('last_name')]);
    }

    public function render()
    {
        return $this->view()->title($this->company->name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('companies.index')" wire:navigate>{{ __('Companies') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $company->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $company->name }}</flux:heading>
            <flux:text>{{ collect([$company->industry, $company->domain])->filter()->join(' · ') }}</flux:text>
        </div>

        @can('update', $company)
            <flux:button icon="pencil-square" :href="route('companies.edit', $company)" wire:navigate>{{ __('Edit') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-500">{{ __('Email') }}</dt><dd>{{ $company->email ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Phone') }}</dt><dd>{{ $company->phone ?? '—' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="text-zinc-500">{{ __('Address') }}</dt>
                        <dd>{{ collect([$company->address_line1, $company->address_line2, $company->city, $company->state, $company->postcode, $company->country])->filter()->join(', ') ?: '—' }}</dd>
                    </div>
                    <div><dt class="text-zinc-500">{{ __('Owner') }}</dt><dd>{{ $company->owner?->name ?? __('Unassigned') }}</dd></div>
                </dl>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Contacts') }}</flux:heading>
                    @can('create', App\Models\Contact::class)
                        <flux:button size="sm" icon="plus" :href="route('contacts.create', ['company' => $company->id])" wire:navigate>{{ __('Add contact') }}</flux:button>
                    @endcan
                </div>

                @forelse ($company->contacts as $contact)
                    <div class="flex items-center justify-between border-b border-zinc-200 pb-2 last:border-0 dark:border-zinc-700">
                        <div>
                            <flux:link :href="route('contacts.show', $contact)" wire:navigate>{{ $contact->full_name }}</flux:link>
                            <flux:text size="sm">{{ $contact->job_title }}</flux:text>
                        </div>
                        <flux:text size="sm">{{ $contact->email }}</flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('No contacts yet.') }}</flux:text>
                @endforelse
            </flux:card>

            <livewire:notes-thread :notable="$company" />
        </div>

        <div>
            <livewire:attachments :model="$company" />
        </div>
    </div>
</div>
