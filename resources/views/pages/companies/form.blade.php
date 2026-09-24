<?php

use App\Livewire\Forms\CompanyForm;
use App\Models\Company;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public CompanyForm $form;

    public function mount(?Company $company = null): void
    {
        if ($company?->exists) {
            $this->authorize('update', $company);
            $this->form->setCompany($company);
        } else {
            $this->authorize('create', Company::class);
        }
    }

    #[Computed]
    public function canAssign(): bool
    {
        return auth()->user()->can('assign', Company::class);
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
        if ($this->form->company) {
            $this->authorize('update', $this->form->company);
        } else {
            $this->authorize('create', Company::class);
        }

        $company = $this->form->save($this->canAssign);

        Flux::toast(variant: 'success', text: __('Company saved.'));

        $this->redirectRoute('companies.show', $company, navigate: true);
    }

    public function render()
    {
        return $this->view()->title($this->form->company ? __('Edit company') : __('New company'));
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('companies.index')" wire:navigate>{{ __('Companies') }}</flux:breadcrumbs.item>
        @if ($form->company)
            <flux:breadcrumbs.item :href="route('companies.show', $form->company)" wire:navigate>{{ $form->company->name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item>{{ __('New') }}</flux:breadcrumbs.item>
        @endif
    </flux:breadcrumbs>

    <flux:heading size="xl" level="1">{{ $form->company ? __('Edit company') : __('New company') }}</flux:heading>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:input wire:model="form.name" :label="__('Name')" required autofocus />

        <div class="grid gap-6 md:grid-cols-2">
            <flux:input wire:model="form.domain" :label="__('Website domain')" placeholder="example.com" />
            <flux:input wire:model="form.industry" :label="__('Industry')" />
            <flux:input wire:model="form.email" :label="__('Email')" type="email" />
            <flux:input wire:model="form.phone" :label="__('Phone')" type="tel" />
        </div>

        <flux:fieldset>
            <flux:legend>{{ __('Address') }}</flux:legend>
            <div class="grid gap-6 md:grid-cols-2">
                <flux:input wire:model="form.address_line1" :label="__('Address line 1')" class="md:col-span-2" />
                <flux:input wire:model="form.address_line2" :label="__('Address line 2')" class="md:col-span-2" />
                <flux:input wire:model="form.city" :label="__('City')" />
                <flux:input wire:model="form.state" :label="__('State / region')" />
                <flux:input wire:model="form.postcode" :label="__('Postcode')" />
                <flux:input wire:model="form.country" :label="__('Country code')" placeholder="AU" maxlength="2" />
            </div>
        </flux:fieldset>

        @if ($this->canAssign)
            <flux:select wire:model="form.owner_id" :label="__('Owner')">
                <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                @foreach ($this->users as $user)
                    <flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <div class="flex gap-2">
            <flux:button variant="primary" type="submit" data-test="save-company-button">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="$form->company ? route('companies.show', $form->company) : route('companies.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
