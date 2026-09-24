<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Gives a CRM record an "attachments" media collection with image previews.
 *
 * Models using this trait must implement Spatie\MediaLibrary\HasMedia.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes(config('crm.attachments.mime_types'))
            ->useDisk(config('media-library.disk_name'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('attachments')
            ->nonOptimized()
            ->fit(Fit::Contain, 300, 300);

        $this->addMediaConversion('preview')
            ->performOnCollections('attachments')
            ->nonOptimized()
            ->fit(Fit::Max, 1200, 1200);
    }
}
