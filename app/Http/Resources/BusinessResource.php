<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
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
            'address' => $this->whenLoaded('address', fn() => AddressResource::make($this->address)),
            'card' => $this->whenLoaded('card', fn() => CardResource::make($this->card)),
            "created_at" => $this->created_at?->format(DATE_FORMAT),
        ];
    }
}
