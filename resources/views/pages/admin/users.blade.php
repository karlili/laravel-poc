<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize(Permission::ManageUsers);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return User::query()
            ->with('roles')
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20);
    }

    public function setRole(int $userId, string $role): void
    {
        $this->authorize(Permission::ManageUsers);

        $role = Role::tryFrom($role) ?? abort(422);
        $user = User::findOrFail($userId);

        if ($user->is(auth()->user()) && $role !== Role::Admin) {
            Flux::toast(variant: 'danger', text: __('You cannot remove your own admin role.'));

            return;
        }

        $user->syncRoles([$role->value]);

        Flux::toast(variant: 'success', text: __(':name is now :role.', ['name' => $user->name, 'role' => $role->label()]));
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" class="max-w-sm" />

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Sign-in') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->isLinkedToEntra())
                            <flux:badge size="sm" color="blue">{{ __('Microsoft') }}</flux:badge>
                        @endif
                        @if ($user->usesLocalPassword())
                            <flux:badge size="sm">{{ __('Password') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:select size="sm" class="max-w-40" wire:change="setRole({{ $user->id }}, $event.target.value)" :aria-label="__('Role for :name', ['name' => $user->name])">
                            @unless ($user->roles->isNotEmpty())
                                <flux:select.option value="" selected disabled>{{ __('No role') }}</flux:select.option>
                            @endunless
                            @foreach (App\Enums\Role::cases() as $role)
                                <flux:select.option :value="$role->value" :selected="$user->hasRole($role->value)">{{ $role->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
