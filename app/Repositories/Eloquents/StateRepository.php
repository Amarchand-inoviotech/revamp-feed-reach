<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\StateResource;
use App\Models\State;
use App\Repositories\Contracts\StateRepositoryContract;

class StateRepository extends BaseRepository implements StateRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    /**
     * Constructor with property promotion
     */
    public function __construct(State $model)
    {
        parent::__construct($model, StateResource::class);
    }

    /**
     * Get search callback for State model
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
