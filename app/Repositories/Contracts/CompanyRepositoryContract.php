<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CompanyRepositoryContract extends BaseRepositoryContract
{

    public function storeModel(array $payload, bool $isResource = true);
    public function updateModel(Model $model, array $payload, bool $isResource = true);
}
