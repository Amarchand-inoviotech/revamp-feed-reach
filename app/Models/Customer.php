<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use ModelTrait, SoftDeletes;
    protected $fillable = [
        'user_id',
        'payment_method_id',
        'gateway_customer_id',
        'extra'
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
