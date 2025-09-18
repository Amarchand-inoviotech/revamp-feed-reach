<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Repositories\Contracts\AddressRepositoryContract;

class AddressRepository extends BaseRepository implements AddressRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(Address $model)
    {
        parent::__construct($model, AddressResource::class);
    }
    /**
     * Get search callback for Address model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }
}
