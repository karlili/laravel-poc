<?php

use App\Models\Contact;
use Livewire\Component;

new class extends Component {
    public Contact $contact;

    public function mount(Contact $contact): void
    {
        $this->authorize('view', $contact);

        $this->contact = $contact->load(['company', 'owner']);
    }

    public function render()
    {
        return $this->view()->title($this->contact->full_name);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('contacts.index')" wire:navigate>{{ __('Contacts') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $contact->full_name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ $contact->full_name }}</flux:heading>
            <flux:text>
                {{ $contact->job_title }}
                @if ($contact->company)
                    @if ($contact->job_title) · @endif
                    <flux:link :href="route('companies.show', $contact->company)" wire:navigate>{{ $contact->company->name }}</flux:link>
                @endif
            </flux:text>
        </div>

        @can('update', $contact)
            <flux:button icon="pencil-square" :href="route('contacts.edit', $contact)" wire:navigate>{{ __('Edit') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-6 lg:col-span-2">
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500">{{ __('Email') }}</dt>
                        <dd>
                            @if ($contact->email)
                                <flux:link :href="'mailto:'.$contact->email">{{ $contact->email }}</flux:link>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div><dt class="text-zinc-500">{{ __('Phone') }}</dt><dd>{{ $contact->phone ?? '—' }}</dd></div>
                    <div><dt class="text-zinc-500">{{ __('Owner') }}</dt><dd>{{ $contact->owner?->name ?? __('Unassigned') }}</dd></div>
                </dl>
            </flux:card>

            <livewire:notes-thread :notable="$contact" />
        </div>

        <div>
            <livewire:attachments :model="$contact" />
        </div>
    </div>
</div>
