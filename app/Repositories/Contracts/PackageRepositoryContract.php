<?php
namespace App\Repositories\Contracts;

use App\Models\Package;

interface PackageRepositoryContract extends BaseRepositoryContract
{
    public function getAllWithAttributes();
}
