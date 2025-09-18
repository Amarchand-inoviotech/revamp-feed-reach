<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
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
            "price" => $this->price,
            "billing_cycle" => $this->billing_cycle,
            "is_agent" => $this->is_agent,
            "created_at" => $this->created_at?->format(DATE_FORMAT),
        ];
    }
}
