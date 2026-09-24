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

    public function delete(int $mediaId): void
    {
        $this->authorize('update', $this->model);

        $this->model->media()->whereKey($mediaId)->firstOrFail()->delete();
        $this->model->unsetRelation('media');
        unset($this->attachments);

        Flux::toast(variant: 'success', text: __('File deleted.'));
    }
}; ?>

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
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $media->id }})" wire:confirm="{{ __('Delete this file?') }}" :aria-label="__('Delete')" />
                @endif
            </li>
        @empty
            <flux:text>{{ __('No files yet.') }}</flux:text>
        @endforelse
    </ul>
</flux:card>
