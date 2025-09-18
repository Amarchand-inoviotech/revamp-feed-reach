<?php

namespace App\Models;

use App\Models\Traits\ModelTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use ModelTrait,SoftDeletes;

    protected $fillable = [
        'name',
        'guard_name',
    ];
}
