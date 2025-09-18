<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use ModelTrait, SoftDeletes;

    protected $fillable = [
        'billing_cycle',
        'user_id',
        'gateway_package_id',
        'getway_subscription_id',
        'start_date',
        'end_date',
        'next_billing_date',
        'status',
        'extra',
    ];

    protected $casts = [
        'extra' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function gatewayPackage()
    {
        return $this->belongsTo(GatewayPackage::class);
    }

    public function package()
    {
        return $this->hasOneThrough(Package::class, GatewayPackage::class, 'id', 'id', 'gateway_package_id', 'package_id');
    }

    public function paymentMethod()
    {
        return $this->hasOneThrough(PaymentMethod::class, GatewayPackage::class, 'id', 'id', 'gateway_package_id', 'payment_method_id');
    }
}
