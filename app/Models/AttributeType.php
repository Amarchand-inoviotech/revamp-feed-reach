<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeType extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function attributes(){
        return $this->hasMany(Attribute::class);
    }

}
