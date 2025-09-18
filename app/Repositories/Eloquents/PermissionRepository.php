<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryContract;
use Illuminate\Database\Eloquent\Collection;

class PermissionRepository extends BaseRepository implements PermissionRepositoryContract
{
    public function __construct(Permission $model)
    {
        parent::__construct($model, PermissionResource::class);
    }
}
