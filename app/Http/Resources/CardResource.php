<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
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
            "last4" => $this->last4,
            "expiry"=> $this->expiry,
            "payment_method"=> $this->whenLoaded('paymentMethod', fn() => PaymentMethodResource::make($this->paymentMethod)),
            "created_at" => $this->created_at?->format(DATE_FORMAT),
        ];
    }
}
