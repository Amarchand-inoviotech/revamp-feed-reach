<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Repositories\Contracts\LeadRepositoryContract;

class LeadRepository extends BaseRepository implements LeadRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
     public function __construct(Lead $model)
    {
        parent::__construct($model, LeadResource::class);
    }
    /**
     * Get search callback for Lead model
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
