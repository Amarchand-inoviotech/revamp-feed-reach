<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'locale'             => $this->locale,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'logo'               => $this->logo_id,
            'address'            => $this->address,
            'invoice_url'        => $this->invoice_url,
            'created_at'         => $this->created_at->format(DATE_FORMAT),
            'deleted_at' => $this->deleted_at?->format(DATE_FORMAT),

            'default_currency'   => $this->whenLoaded('defaultCurrency', fn() => CurrencyResource::make($this->defaultCurrency), null),
            'logo'               => $this->whenLoaded('logo', fn() => AttachmentResource::make($this->logo), null),
        ];
    }
}
