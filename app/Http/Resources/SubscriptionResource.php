<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'billing_cycle' => $this->billing_cycle,
            'gateway_subscription_id' => $this->getway_subscription_id,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'next_billing_date' => $this->next_billing_date?->format('Y-m-d'),
            'status' => $this->status,
            'extra' => $this->extra,
            'created_at' => $this->created_at?->format(DATE_FORMAT),
            
            // Relations
            'user' => $this->whenLoaded('user', fn() => UserResource::make($this->user)),
            'gateway_package' => $this->whenLoaded('gatewayPackage', fn() => GatewayPackageResource::make($this->gatewayPackage)),
            'package' => $this->whenLoaded('package', fn() => PackageResource::make($this->package)),
            'payment_method' => $this->whenLoaded('paymentMethod', fn() => PaymentMethodResource::make($this->paymentMethod)),
        ];
    }
}
