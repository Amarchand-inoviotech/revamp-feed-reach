<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'attribute_type_id',
        'content'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    public function attributeType(){
        return $this->belongsTo(AttributeType::class);
    }

    public function packages(){
        return $this->belongsToMany(Package::class);
    }

}
