<?php

namespace App\Repositories\Contracts;

interface BusinessRepositoryContract extends BaseRepositoryContract
{
    public function createBusiness(array $payload, bool $isResource = true);

}
