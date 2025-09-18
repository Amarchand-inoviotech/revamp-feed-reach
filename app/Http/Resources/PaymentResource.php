<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id"=> $this->uuid,
            "transaction_id"=> $this->transaction_id,
            "content"=> $this->content,
            "payment_status"=> $this->payment_status,
            "type"=> $this->type,
            "cc_type"=> $this->cc_type,
            "status"=> $this->status,
            "invoice"=> $this->whenLoaded('invoice', fn() => InvoiceResource::make($this->invoice)),
            "payment_method"=> $this->whenLoaded('paymentMethod', fn() => PaymentMethodResource::make($this->paymentMethod)),
        ];
    }
}
