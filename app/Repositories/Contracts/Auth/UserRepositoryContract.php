<?php

namespace App\Repositories\Contracts\Auth;

use App\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Model;

interface UserRepositoryContract extends BaseRepositoryContract
{
    public function forgotPassword(array $payload);
    public function resetPassword(array $payload);

    public function login(array $payload);
}
