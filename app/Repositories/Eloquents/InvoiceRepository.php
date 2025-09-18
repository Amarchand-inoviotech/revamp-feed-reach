<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryContract;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryContract
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model, InvoiceResource::class);
    }
}
