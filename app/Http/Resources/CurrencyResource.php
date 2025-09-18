<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'created_at' => $this->created_at?->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),
        ];
    }
}
