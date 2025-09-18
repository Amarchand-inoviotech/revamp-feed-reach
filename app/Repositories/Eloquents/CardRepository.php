<?php

namespace App\Repositories\Eloquents;

use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Repositories\Contracts\CardRepositoryContract;

class CardRepository extends BaseRepository implements CardRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];

    public function __construct(Card $model) {
        $this->model = $model;
        $this->resource = CardResource::class;
    }

    /**
     * Get search callback for Card model
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
