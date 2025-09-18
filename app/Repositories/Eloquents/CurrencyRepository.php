<?php

namespace App\Repositories\Eloquents;

use App\DTOs\RequestParams;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryContract;

class CurrencyRepository extends BaseRepository implements CurrencyRepositoryContract
{
    public function __construct(Currency $model)
    {
        parent::__construct($model, CurrencyResource::class);
    }


    /**
     * Get search callback for currency model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function ($query, $search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('symbol', "{$search}");
        };
    }

    /**
     * Default relations to load
     */
    protected array $defaultRelations = [];
}
