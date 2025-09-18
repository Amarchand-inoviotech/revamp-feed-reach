<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatewayPackageResource extends JsonResource
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
            'gateway_id' => $this->gateway_id,
            'created_at' => $this->created_at?->format(DATE_FORMAT),
            
            // Relations
            'package' => $this->whenLoaded('package', fn() => PackageResource::make($this->package)),
            'payment_method' => $this->whenLoaded('paymentMethod', fn() => PaymentMethodResource::make($this->paymentMethod)),
            'subscriptions' => $this->whenLoaded('subscriptions', fn() => SubscriptionResource::collection($this->subscriptions)),
        ];
    }
}
