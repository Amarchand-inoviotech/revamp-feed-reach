<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\DTOs\RequestParams;

trait HandlesPaginationTrait
{
    /**
     * Default chunk size for large datasets
     */
    protected int $defaultChunkSize = 1000;

    /**
     * Execute pagination or chunking based on parameters
     *
     * @param Builder $query
     * @param RequestParams $params
     * @return mixed
     */
    protected function executePagination(Builder $query, RequestParams $params)
    {
        // If pagination is requested, use Laravel's paginator
        if ($params->paginate) {
            return $query->paginate($params->perPage);
        }

        // If a specific limit is set
        if ($params->perPage > 0) {
            return $query->limit($params->perPage)->get();
        }

        // For potentially large datasets, always use chunking
        // This avoids memory issues without needing to count
        return $this->chunkResults($query);
    }

    /**
     * Process results in chunks to handle large datasets efficiently
     *
     * @param Builder $query
     * @param int|null $chunkSize
     * @return Collection
     */
    protected function chunkResults(Builder $query, ?int $chunkSize = null): Collection
    {
        $collection = new Collection();
        $chunkSize = $chunkSize ?? $this->defaultChunkSize;

        // Use cursor() for better memory efficiency
        foreach ($query->cursor() as $model) {
            $collection->push($model);
        }

        return $collection;
    }

    /**
     * Set custom chunk size
     *
     * @param int $size
     * @return self
     */
    protected function setChunkSize(int $size): self
    {
        $this->defaultChunkSize = $size;
        return $this;
    }
}
