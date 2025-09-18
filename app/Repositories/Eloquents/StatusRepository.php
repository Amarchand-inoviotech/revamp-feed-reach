<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\StatusResource;
use App\Models\Status;
use App\Repositories\Contracts\StatusRepositoryContract;

class StatusRepository extends BaseRepository implements StatusRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    public function __construct(Status $model)
    {
        parent::__construct($model, StatusResource::class);
    }

    /**
     * Get search callback for status model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }
}
