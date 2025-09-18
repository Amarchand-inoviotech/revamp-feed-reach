<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryContract;
use Illuminate\Database\Eloquent\Model;

class RoleRepository extends BaseRepository implements RoleRepositoryContract
{
    public function __construct(Role $model)
    {
        parent::__construct($model, RoleResource::class);
    }

    public function storeModel(array $payload, bool $isResource = true)
    {
        $model = $this->model;
        $model->toFill($payload);
        $model->save();
        if (isset($payload['permissions']) && is_array($payload['permissions'])) {
            $model->syncPermissions($payload['permissions']);
        }

        $model->load('permissions');

        return $this->resource ? $this->resource::make($model) : $model;
    }

    public function updateModel(Model $model, array $payload, bool $isResource = true)
    {
        $model->toFill($payload);
        $model->save();
        if (isset($payload['permissions']) && is_array($payload['permissions'])) {
            $model->syncPermissions($payload['permissions']);
        }
        $model->load('permissions');

        return $this->resource ? $this->resource::make($model) : $model;
    }
}
