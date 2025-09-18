<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use ModelTrait, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'payment_method_id',
        'name',
        'descriptor',
        'email',
        'daily_limit',
        'monthly_limit',
        'status',
        'meta',
    ];

    protected $casts = [
        'status' => 'boolean',
        'meta' => 'array'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }


    public function company(){
        return $this->belongsTo(Company::class);
    }
}
