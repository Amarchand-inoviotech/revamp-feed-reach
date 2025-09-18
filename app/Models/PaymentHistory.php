<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentHistory extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'payment_id',
        'amount',
        'new_transcation_id',
        'status'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}
