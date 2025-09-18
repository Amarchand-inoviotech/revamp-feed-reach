<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'gateway_id',
        'note',
        'amount',
        'usd_amount',
        'type',
        'response',
        'client_ip',
        'cc_type',
        'status'
    ];

    protected $casts = [
        'response' => 'array',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function payment_method(){
        return $this->belongsTo(PaymentMethod::class);
    }

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }
}
