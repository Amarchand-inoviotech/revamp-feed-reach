<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GatewayPackage extends Model
{
    use ModelTrait, SoftDeletes;

    protected $fillable = [
        'payment_method_id',
        'package_id',
        'gateway_id',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
