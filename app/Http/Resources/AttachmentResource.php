<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'path' => $this->path ? asset('storage/' . $this->path) : null,
            'cloud_path' => $this->cloud_path,
            'width' => $this->width,
            'height' => $this->height,
            'orientation' => $this->orientation,
            'comment' => $this->comment,
            'created_at' => $this->created_at->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),
        ];
    }
}
