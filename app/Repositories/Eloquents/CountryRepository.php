<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\CountryResource;
use App\Models\Country;
use App\Repositories\Contracts\CountryRepositoryContract;

class CountryRepository extends BaseRepository implements CountryRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */

    public function __construct(Country $model)
    {
        parent::__construct($model, CountryResource::class);
    }

    /**
     * Get search callback for Country model
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
