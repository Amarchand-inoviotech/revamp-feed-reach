<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use ModelTrait, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'llc_address_id',
        'business_address_id',
        'card_id',
        'service_id',
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


    public function address(){
        return $this->belongsTo(Address::class);
    }

    public function card(){
        return $this->belongsTo(Card::class);
    }

    public function service(){
        return $this->belongsTo(Service::class);
    }


}
