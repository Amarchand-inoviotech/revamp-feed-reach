<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface UserRepositoryContract extends BaseRepositoryContract
{
    public function forgotPassword(array $payload);
    public function resetPassword(array $payload);
    public function storeModel(array $payload, bool $isResource = true);
    public function updateModel(Model $model, array $payload, bool $isResource = true);
}
