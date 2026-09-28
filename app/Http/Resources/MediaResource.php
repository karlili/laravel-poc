<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An attachment, with URLs that go through MediaDownloadController's
 * authorisation check.
 *
 * @mixin Media
 */
class MediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasThumb = str_starts_with((string) $this->mime_type, 'image/') && $this->hasGeneratedConversion('thumb');

        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->human_readable_size,
            'url' => route('media.show', $this->resource),
            'thumb_url' => $hasThumb ? route('media.show', [$this->resource, 'thumb']) : null,
            'created_at_diff' => $this->created_at?->diffForHumans(),
        ];
    }
}
