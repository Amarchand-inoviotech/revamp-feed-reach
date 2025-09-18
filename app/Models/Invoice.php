<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'author_id',
        'author_type',
        'model_id',
        'model_type',
        'currency_id',
        'account_id',
        'company_id',
        'note',
        'amount',
        'usd_amount',
        'expires_at',
        'status',
        'payment_attempts',
        'last_payment_attempt_at',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function company(){
        return $this->belongsTo(Company::class);
    }

    public function account(){
        return $this->belongsTo(Account::class);
    }

    public function author(){
        return $this->morphTo();
    }

    public function model(){
        return $this->morphTo();
    }

    public function payments(){
        return $this->hasMany(Payment::class);
    }

    public function currency(){
        return $this->belongsTo(Currency::class);
    }
}
