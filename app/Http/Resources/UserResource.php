<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'gender' => $this->gender,
            'dob' => $this->dob,
            'phone' => $this->phone,
            'enable_two_factor' => $this->two_factor ? true : false,
            'enable_notification' => $this->notification ? true : false,
            'email_verified_at' =>  $this->email_verified_at?->format(DATE_FORMAT),
            'phone_verified_at' =>  $this->phone_varified_at?->format(DATE_FORMAT),
            'created_at' => $this->created_at->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),

            'avatar' => $this->whenLoaded('avatar', fn() => AttachmentResource::make($this->avatar)),
            'company' => $this->whenLoaded('company', fn() => CompanyResource::make($this->company)),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
