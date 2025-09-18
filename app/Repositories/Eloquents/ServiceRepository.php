<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryContract;

class ServiceRepository extends BaseRepository implements ServiceRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(Service $model)
    {
        parent::__construct($model, ServiceResource::class);
    }

    /**
     * Get search callback for Service model
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
