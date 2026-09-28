<?php

use App\Actions\Attachments\StoreAttachment;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Note;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Model $notable;

    public string $body = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** The note awaiting confirmation in the delete modal. */
    #[Locked]
    public ?int $deletingId = null;

    public function mount(Model $notable): void
    {
        abort_unless($notable instanceof Company || $notable instanceof Contact, 404);
        $this->authorize('view', $notable);

        $this->notable = $notable;
    }

    #[Computed]
    public function canCreate(): bool
    {
        return auth()->user()->can('create', [Note::class, $this->notable]);
    }

    /**
     * @return Collection<int, Note>
     */
    #[Computed]
    public function notes(): Collection
    {
        return $this->notable->notes()->with(['author', 'media'])->get();
    }

    public function add(StoreAttachment $store): void
    {
        $this->authorize('create', [Note::class, $this->notable]);

        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
            'uploads' => ['array', 'max:5'],
            'uploads.*' => StoreAttachment::rules(),
        ]);

        $note = new Note(['body' => $this->body]);
        $note->author()->associate(auth()->user());
        $this->notable->notes()->save($note);

        foreach ($this->uploads as $upload) {
            $store($note, $upload);
        }

        $this->reset('body', 'uploads');
        unset($this->notes);

        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    public function confirmDelete(int $noteId): void
    {
        $note = $this->notable->notes()->findOrFail($noteId);
        $this->authorize('delete', $note);

        $this->deletingId = $note->id;

        Flux::modal('delete-note')->show();
    }

    public function delete(): void
    {
        $note = $this->notable->notes()->findOrFail($this->deletingId);
        $this->authorize('delete', $note);

        $note->delete();
        $this->reset('deletingId');
        unset($this->notes);

        Flux::modal('delete-note')->close();
        Flux::toast(variant: 'success', text: __('Note deleted.'));
    }
}; ?>

@placeholder
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
        <flux:skeleton.group animate="shimmer" class="space-y-2">
            <flux:skeleton.line />
            <flux:skeleton.line class="w-2/3" />
        </flux:skeleton.group>
    </flux:card>
@endplaceholder

<flux:card class="space-y-4">
    <flux:heading size="lg">{{ __('Notes') }}</flux:heading>

    @if ($this->canCreate)
        <form wire:submit="add" class="space-y-3">
            <flux:textarea wire:model="body" :label="__('Add a note')" rows="3" />
            <flux:input type="file" wire:model="uploads" multiple size="sm" :aria-label="__('Attach files')" />
            <flux:error name="uploads.*" />
            <flux:button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="uploads,add">
                {{ __('Add note') }}
            </flux:button>
        </form>
    @endif

    <div class="space-y-4">
        @forelse ($this->notes as $note)
            <div wire:key="note-{{ $note->id }}" class="border-t border-zinc-200 pt-4 first:border-0 first:pt-0 dark:border-zinc-700">
                <div class="flex items-start justify-between gap-2">
                    <flux:text size="sm">
                        <span class="font-medium text-zinc-800 dark:text-white">{{ $note->author?->name ?? __('Former user') }}</span>
                        · {{ $note->created_at?->diffForHumans() }}
                    </flux:text>

                    @can('delete', $note)
                        <flux:button size="xs" variant="ghost" icon="trash" wire:click="confirmDelete({{ $note->id }})" :aria-label="__('Delete note')" />
                    @endcan
                </div>

                <p class="mt-1 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-200">{{ $note->body }}</p>

                @if ($note->media->isNotEmpty())
                    <ul class="mt-2 flex flex-wrap gap-2">
                        @foreach ($note->media as $media)
                            <li>
                                <flux:badge size="sm" icon="paper-clip" as="a" :href="route('media.show', $media)">{{ $media->file_name }}</flux:badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <flux:text>{{ __('No notes yet.') }}</flux:text>
        @endforelse
    </div>

    <flux:modal name="delete-note" class="min-w-[22rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete this note?') }}</flux:heading>
            <flux:text>{{ __('The note and its attached files are removed.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
