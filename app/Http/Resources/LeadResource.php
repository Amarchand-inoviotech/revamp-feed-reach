<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->uuid,
            "name" => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'business_name' => $this->business_name,
            'business_url' => $this->business_url,
            'package' => $this->whenLoaded('package', fn() => PackageResource::make($this->package)),
            "created_at" => $this->created_at?->format(DATE_FORMAT),
        ];
    }
}
