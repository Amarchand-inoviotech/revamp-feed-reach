<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributePackage extends Model
{
    protected $table = 'attribute_package';
    public $timestamps = false; // if pivot table has no timestamps

    protected $fillable = [
        'attribute_id',
        'package_id',
    ];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
