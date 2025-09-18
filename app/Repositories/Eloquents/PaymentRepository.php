<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryContract;

class PaymentRepository extends BaseRepository implements PaymentRepositoryContract
{
    public function __construct(Payment $model)
    {
        parent::__construct($model, PaymentResource::class);
    }
}
