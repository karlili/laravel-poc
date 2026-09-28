<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'domain' => $this->domain,
            'industry' => $this->industry,
            'email' => $this->email,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'owner_id' => $this->owner_id,
            'owner' => UserSummaryResource::make($this->whenLoaded('owner')),
            'contacts_count' => $this->whenCounted('contacts'),
            'contacts' => ContactResource::collection($this->whenLoaded('contacts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'can' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'delete' => (bool) $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
