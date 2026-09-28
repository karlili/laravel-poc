<?php

use App\Actions\Attachments\StoreAttachment;
use Flux\Flux;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;

new class extends Component {
    use WithFileUploads;

    #[Locked]
    public Model $model;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    /** The file awaiting confirmation in the delete modal. */
    #[Locked]
    public ?int $deletingId = null;

    public function mount(Model $model): void
    {
        abort_unless($model instanceof HasMedia, 404);
        $this->authorize('view', $model);

        $this->model = $model;
    }

    #[Computed]
    public function canManage(): bool
    {
        return auth()->user()->can('update', $this->model);
    }

    #[Computed]
    public function attachments(): MediaCollection
    {
        return $this->model->getMedia('attachments')->sortByDesc('created_at');
    }

    public function save(StoreAttachment $store): void
    {
        $this->authorize('update', $this->model);

        $this->validate([
            'uploads' => ['required', 'array', 'max:10'],
            'uploads.*' => StoreAttachment::rules(),
        ]);

        foreach ($this->uploads as $upload) {
            $store($this->model, $upload);
        }

        $this->reset('uploads');
        $this->model->unsetRelation('media');
        unset($this->attachments);

        Flux::toast(variant: 'success', text: __('Files uploaded.'));
    }

    public function confirmDelete(int $mediaId): void
    {
        $this->authorize('update', $this->model);

        $this->deletingId = $this->model->media()->whereKey($mediaId)->firstOrFail()->getKey();

        Flux::modal('delete-attachment')->show();
    }

    public function delete(): void
    {
        $this->authorize('update', $this->model);

        $this->model->media()->whereKey($this->deletingId)->firstOrFail()->delete();
        $this->reset('deletingId');
        $this->model->unsetRelation('media');
        unset($this->attachments);

        Flux::modal('delete-attachment')->close();
        Flux::toast(variant: 'success', text: __('File deleted.'));
    }
}; ?>

@placeholder
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Attachments') }}</flux:heading>
        <flux:skeleton.group animate="shimmer" class="space-y-3">
            <flux:skeleton class="h-12 w-full" />
            <flux:skeleton class="h-12 w-full" />
        </flux:skeleton.group>
    </flux:card>
@endplaceholder

<flux:card class="space-y-4">
    <flux:heading size="lg">{{ __('Attachments') }}</flux:heading>

    @if ($this->canManage)
        <form wire:submit="save" class="space-y-3">
            <flux:input type="file" wire:model="uploads" multiple :label="__('Upload files')" />
            <flux:error name="uploads.*" />
            <flux:text size="sm">
                {{ __('Documents and images up to :size MB.', ['size' => intdiv(config('crm.attachments.max_size_kb'), 1024)]) }}
            </flux:text>
            <flux:button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="uploads,save">
                {{ __('Upload') }}
            </flux:button>
        </form>
    @endif

    <ul class="space-y-3">
        @forelse ($this->attachments as $media)
            <li wire:key="media-{{ $media->id }}" class="flex items-center gap-3">
                @if (str_starts_with((string) $media->mime_type, 'image/') && $media->hasGeneratedConversion('thumb'))
                    <img src="{{ route('media.show', [$media, 'thumb']) }}" alt="" class="size-12 rounded object-cover" loading="lazy" />
                @else
                    <div class="flex size-12 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-700">
                        <flux:icon.document class="size-6 text-zinc-500" />
                    </div>
                @endif

                <div class="min-w-0 flex-1">
                    <flux:link :href="route('media.show', $media)" class="block truncate">{{ $media->file_name }}</flux:link>
                    <flux:text size="sm">{{ $media->human_readable_size }} · {{ $media->created_at?->diffForHumans() }}</flux:text>
                </div>

                @if ($this->canManage)
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="confirmDelete({{ $media->id }})" :aria-label="__('Delete')" />
                @endif
            </li>
        @empty
            <flux:text>{{ __('No files yet.') }}</flux:text>
        @endforelse
    </ul>

    <flux:modal name="delete-attachment" class="min-w-[22rem]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete :name?', ['name' => $this->attachments->firstWhere('id', $deletingId)?->file_name]) }}</flux:heading>
            <flux:text>{{ __('The file is removed permanently.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</flux:card>
