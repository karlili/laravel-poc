<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $user = auth()->user();

        return [
            'companies' => $user->can('viewAny', Company::class) ? Company::count() : 0,
            'contacts' => $user->can('viewAny', Contact::class) ? Contact::count() : 0,
            'mine' => Company::where('owner_id', $user->id)->count() + Contact::where('owner_id', $user->id)->count(),
        ];
    }

    /**
     * @return Collection<int, Note>
     */
    #[Computed]
    public function recentNotes(): Collection
    {
        if (! auth()->user()->can('viewAny', Company::class) || ! auth()->user()->can('viewAny', Contact::class)) {
            return new Collection;
        }

        return Note::with(['author', 'notable'])->latest()->limit(8)->get();
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>

    <div class="grid gap-4 md:grid-cols-3">
        <flux:card>
            <flux:text>{{ __('Companies') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['companies']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text>{{ __('Contacts') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['contacts']) }}</flux:heading>
        </flux:card>
        <flux:card>
            <flux:text>{{ __('Records assigned to you') }}</flux:text>
            <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['mine']) }}</flux:heading>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Recent notes') }}</flux:heading>

        @forelse ($this->recentNotes as $note)
            <div wire:key="recent-note-{{ $note->id }}" class="border-t border-zinc-200 pt-3 first:border-0 first:pt-0 dark:border-zinc-700">
                <flux:text size="sm">
                    <span class="font-medium text-zinc-800 dark:text-white">{{ $note->author?->name ?? __('Former user') }}</span>
                    {{ __('on') }}
                    @if ($note->notable instanceof App\Models\Company)
                        <flux:link :href="route('companies.show', $note->notable)" wire:navigate>{{ $note->notable->name }}</flux:link>
                    @elseif ($note->notable instanceof App\Models\Contact)
                        <flux:link :href="route('contacts.show', $note->notable)" wire:navigate>{{ $note->notable->full_name }}</flux:link>
                    @endif
                    · {{ $note->created_at?->diffForHumans() }}
                </flux:text>
                <p class="mt-1 line-clamp-2 text-sm text-zinc-700 dark:text-zinc-200">{{ $note->body }}</p>
            </div>
        @empty
            <flux:text>{{ __('No notes yet.') }}</flux:text>
        @endforelse
    </flux:card>
</div>
