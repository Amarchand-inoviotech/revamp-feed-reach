<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StatusResource extends JsonResource
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
            "model" => $this->model,
            "name" => $this->name,
            "color" => $this->color,
            "icon" => $this->icon,
            "created_at" => $this->created_at?->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),
        ];
    }
}
