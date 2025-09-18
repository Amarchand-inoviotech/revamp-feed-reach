<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            "guard_name" => $this->guard_name,
            "created_at" => $this->created_at?->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),
            // Only include permissions if they're loaded and not empty
            "permissions" => $this->when(
                $this->relationLoaded('permissions') && $this->permissions->isNotEmpty(),
                fn() => PermissionResource::collection($this->permissions)
            ),
        ];
    }
}
