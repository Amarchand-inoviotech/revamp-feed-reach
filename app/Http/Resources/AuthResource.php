<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Unset role permissions relation since we've already merged them
        if ($this->resource->relationLoaded('roles')) {
            // We need to unset the permissions relation on each role
            foreach ($this->resource->roles as $role) {
                $role->unsetRelation('permissions');
            }
        }

        $token = $this->additional['token'] ?? null;
        $permissions = $this->additional['permissions'] ?? null;
        $devices = $this->additional['devices'] ?? null;
        return [
            "id" => $this->uuid,
            "username" => $this->username,
            "name" => $this->name,
            "email" => $this->email,
            "gender" => $this->gender,
            "dob" => $this->dob,
            "phone" => $this->phone,
            "enable_two_factor" => $this->two_factor ? true : false,
            "enable_notification" => $this->notification ? true : false,
            "email_verified_at" => $this->email_verified_at?->format(DATE_FORMAT),
            "phone_verified_at" => $this->phone_verified_at?->format(DATE_FORMAT),
            "created_at" => $this->created_at->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),
            "access_token" => $this->when($token,$token),
            "devices" => $this->when($devices,$devices),
            'avatar' => $this->whenLoaded('avatar', fn() => AttachmentResource::make($this->avatar)),
            'permissions' => $this->when($permissions !== null, function () use($permissions){
                return PermissionResource::collection($permissions);
            }),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }

}
