<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'symbol'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

}
