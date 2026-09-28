<?php

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => UserSummaryResource::make($this->whenLoaded('author')),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_diff' => $this->created_at?->diffForHumans(),
            'attachments' => MediaResource::collection($this->whenLoaded('media')),
            'can' => [
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
