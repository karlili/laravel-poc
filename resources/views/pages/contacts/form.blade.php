<?php

use App\Livewire\Forms\ContactForm;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ContactForm $form;

    public function mount(?Contact $contact = null): void
    {
        if ($contact?->exists) {
            $this->authorize('update', $contact);
            $this->form->setContact($contact);
        } else {
            $this->authorize('create', Contact::class);
            $this->form->company_id = Company::whereKey(request()->integer('company'))->value('id');
        }
    }

    #[Computed]
    public function canAssign(): bool
    {
        return auth()->user()->can('assign', Contact::class);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::orderBy('name')->get(['id', 'name']);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return $this->canAssign ? User::orderBy('name')->get(['id', 'name']) : collect();
    }

    public function save(): void
    {
        if ($this->form->contact) {
            $this->authorize('update', $this->form->contact);
        } else {
            $this->authorize('create', Contact::class);
        }

        $contact = $this->form->save($this->canAssign);

        Flux::toast(variant: 'success', text: __('Contact saved.'));

        $this->redirectRoute('contacts.show', $contact, navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->form->contact ? __('Edit contact') : __('New contact'));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('contacts.index')" wire:navigate>{{ __('Contacts') }}</flux:breadcrumbs.item>
        @if ($form->contact)
            <flux:breadcrumbs.item :href="route('contacts.show', $form->contact)" wire:navigate>{{ $form->contact->full_name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item>{{ __('New') }}</flux:breadcrumbs.item>
        @endif
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1">{{ $form->contact ? __('Edit contact') : __('New contact') }}</flux:heading>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid gap-6 md:grid-cols-2">
            <flux:input wire:model="form.first_name" :label="__('First name')" required autofocus />
            <flux:input wire:model="form.last_name" :label="__('Last name')" required />
            <flux:input wire:model="form.email" :label="__('Email')" type="email" />
            <flux:input wire:model="form.phone" :label="__('Phone')" type="tel" />
            <flux:input wire:model="form.job_title" :label="__('Job title')" />

            <flux:select wire:model="form.company_id" :label="__('Company')">
                <flux:select.option value="">{{ __('No company') }}</flux:select.option>
                @foreach ($this->companies as $company)
                    <flux:select.option :value="$company->id">{{ $company->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->canAssign)
            <flux:select wire:model="form.owner_id" :label="__('Owner')">
                <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                @foreach ($this->users as $user)
                    <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit" data-test="save-contact-button">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="$form->contact ? route('contacts.show', $form->contact) : route('contacts.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
