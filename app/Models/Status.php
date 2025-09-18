<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'model',
        'name',
        'color',
        'icon'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];
}
