<?php

namespace App\Repositories\Eloquents;

use App\DTOs\RequestParams;
use App\QueryBuilder\QueryBuilder;
use App\Traits\HandlesPaginationTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseRepository implements BaseRepositoryContract
{
    use HandlesPaginationTrait;

    /**
     * Default relations to load
     *
     * @var array
     */
    protected array $defaultRelations = [];

    /**
     * BaseRepository constructor.
     *
     * @param Model $model
     */
    public function __construct(
           protected Model $model,
        protected ?string $resource = null
    ){}



    /**
     * Get search callback for the model
     * Override this method in child classes to provide custom search functionality
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }

    /**
     * Get all records with search, sorting, and pagination
     *
     * @param RequestParams $params
     * @return mixed
     */
    public function getAll(RequestParams $params)
    {
        // Add default relations if needed
        if (!empty($this->defaultRelations)) {
            $params->relations = array_merge($params->relations, $this->defaultRelations);
        }

        // Set search callback if needed and not already provided
        if ($params->search !== null && $params->searchCallback === null) {
            $params->searchCallback = $this->getSearchCallback();
        }

        // Build and execute query
        $query = (new QueryBuilder($this->model->newQuery(), $params))->apply();
        $result = $this->executePagination($query, $params);

        return $this->resource ? $this->resource::collection($result) : $result;
    }

    /**
     * Get first record
     *
     * @param array $columns
     * @param array $relations
     * @param array $where
     * @return Model
     */
    public function getFirst(array $where = [], array $relations = [], array $columns = ['*'], bool $isResource = true)
    {
        $model = $this->model
            ->when($columns, function ($q, $columns) {
                $q->select($columns);
            })
            ->when($relations, function ($q, $relations) {
                $q->with($relations);
            })
            ->when($where, function ($q, $where) {
                $q->where($where);
            })->firstOrFail();


        return  $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Get all trashed models.
     *
     * @return Collection
     */
    public function allTrashed(): Collection
    {
        return $this->model->onlyTrashed()->get();
    }

    /**
     * Find model by id.
     *
     * @param int $modelId
     * @param array $relations
     * @param array $columns
     * @param array $appends
     * @return Model
     */
    public function findById(int $modelId, array $relations = [], array $columns = ['*'], array $appends = []): ?Model
    {
        return $this->model
            ->when($columns, function ($q) use ($columns) {
                $q->select($columns);
            })->when($relations, function ($q) use ($relations) {
                $q->with($relations);
            })->findOrFail($modelId)->append($appends);
    }

    /**
     * Find trashed model by id.
     *
     * @param int $modelId
     * @param array $relations (optional) load model relations
     * @param array $columns (optional) select model columns
     * @return Model
     */
    public function findTrashedById(int $modelId, array $relations = [], array $columns = ['*'],): ?Model
    {
        return $this->model
            ->withTrashed()
            ->when($columns, function ($q, $columns) {
                $q->select($columns);
            })
            ->when($relations, function ($q, $relations) {
                $q->with($relations);
            })
            ->findOrFail($modelId);
    }

    /**
     * Find only trashed model by id.
     *
     * @param int $modelId
     * @return Model
     */
    public function findOnlyTrashedById(int $modelId): ?Model
    {
        return $this->model->onlyTrashed()->findOrFail($modelId);
    }


    /**
     * Update existing model.
     *
     * @param int $modelId
     * @param array $payload
     * @return Model
     */
    public function updateById(int $modelId, array $payload): ?Model
    {
        $model = $this->findById($modelId);

        return $this->updateModel($model, $payload, false);
    }

    /**
     * Delete model by id.
     *
     * @param int $modelId
     * @return bool
     */
    public function deleteById(int $modelId): bool
    {
        return $this->findById($modelId)->delete();
    }

    /**
     * Restore model by id.
     *
     * @param int $modelId
     * @return bool
     */
    public function restoreById(int $modelId): bool
    {
        return $this->findOnlyTrashedById($modelId)->restore();
    }

    /**
     * Permanently delete model by id.
     *
     * @param int $modelId
     * @param array $relations
     * @return Model|JsonResource
     */
    public function permanentlyDeleteById(int $modelId, array $relations = [])
    {
        if (method_exists($this->model, 'trashed')) {
            $model = $this->findTrashedById($modelId);
        } else {
            $model = $this->findById($modelId);
        }

        return $this->permanentlyDeleteModel($model, $relations);
    }

    /**
     * delete child relation records
     *
     * @param object $relation
     * @return bool
     */
    protected function deleteChildRecords($relation)
    {
        // delete nested relations
        if (method_exists($relation, "getRelations")) {
            foreach ($relation->getRelations() as $nestedRelation) {
                $this->deleteChildRecords($nestedRelation);
            }
        }

        $response = false;
        // is collection
        if ($relation instanceof Collection) {

            // has data
            if ($relation->count() > 0) {
                foreach ($relation as $object) {
                    method_exists($object, 'trashed') ? $object->forceDelete() : $object->delete();
                }
                return true;
            }
        } else {
            $response = method_exists($relation, 'trashed') ? $relation->forceDelete() : $relation->delete();
        }
        return  $response;
    }

    /**
     * Store Model
     *
     * @param array $payload
     * @return Model|JsonResource
     *
     */

    public function storeModel(array $payload, bool $isResource = true)
    {
        $model = $this->model;
        if (method_exists($model, "toFill")) {
            $model->toFill($payload);
        } else {
            $model->fill($payload);
        }
        $model->save();

        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Show model
     * @param Model $model
     * @param array $relations
     * @return Model|JsonResource
     *
     */
    public function showModel(Model $model, array $relations = [], bool $isResource = true)
    {
        $model->load($relations);
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Update model
     * @param Model $model
     * @param array $payload
     * @return Model|JsonResource
     *
     */
    public function updateModel(Model $model, array $payload, bool $isResource = true)
    {
        if (method_exists($model, "toFill")) {
            $model->toFill($payload);
        } else {
            $model->fill($payload);
        }
        $model->save();

        $model->fresh();
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Restore Model
     *
     * @param Model $model
     * @return Model|JsonResource
     *
     */
    public function restoreModel(Model $model, bool $isResource = true)
    {
        $model->restore();
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Soft Delete Model
     *
     * @param Model $model
     * @return Model|JsonResource
     *
     */
    public function softDeleteModel(Model $model, bool $isResource = true)
    {
        $model->delete();
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    /**
     * Bulk Delete Model
     *
     * @param array $ids
     * @return bool
     */
    public function bulkDeleteModel(array $ids)
    {
        return $this->model->whereIn('uuid', $ids)->delete();
    }

    /**
     * Delete Model
     *
     * @param Model $model
     * @param array $relations
     * @return Model|JsonResource
     *
     */
    public function permanentlyDeleteModel(
        Model $model,
        array $relations = []
    ) {

        $model->load($relations);

        // delete nested relations
        if (method_exists($model, "getRelations")) {

            foreach ($model->getRelations() as  $relation) {
                $this->deleteChildRecords($relation);
            }
        }



        // delete parent model
        if (method_exists($model, 'trashed')) {
            $model->forceDelete();
        } else {
            $model->delete();
        }

        return $this->resource ? $this->resource::make($model) : $model;
    }



    /**
     * Export data to CSV using the same filters as getAll but without pagination
     * Uses chunking/streaming for memory efficiency with large datasets
     *
     * @param \App\DTOs\RequestParams $params
     * @param array $columns Associative array where keys are column names and values are display names
     * @param int $chunkSize Number of records to process at once
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportCsv(\App\DTOs\RequestParams $params, array $columns, int $chunkSize = 1000)
    {
        // Force disable pagination for export
        $params->paginate = false;

        // Add default relations if needed
        if (!empty($this->defaultRelations)) {
            $params->relations = array_merge($params->relations, $this->defaultRelations);
        }

        // Set search callback if needed and not already provided
        if ($params->search !== null && $params->searchCallback === null) {
            $params->searchCallback = $this->getSearchCallback();
        }

        // Get column keys for select statement
        $selectColumns = array_keys($columns);

        // Build query with all filters but without pagination
        $query = (new QueryBuilder($this->model->newQuery(), $params))->apply();

        // Select only the needed columns
        $query->select($selectColumns);

        // Generate filename
        $className = strtolower(basename(get_class($this->model)));
        $fileName = "{$className}_" . date('Y-m-d') . ".csv";

        // Set HTTP headers for CSV download
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        // Create streaming response
        return response()->stream(function () use ($query, $columns, $chunkSize) {
            // Open output stream
            $file = fopen('php://output', 'w');

            // Write CSV header row
            fputcsv($file, array_values($columns));

            // Process records in chunks to avoid memory issues
            $query->chunk($chunkSize, function ($records) use ($file, $columns) {
                foreach ($records as $record) {
                    $row = [];
                    // Map each column to its value
                    foreach ($columns as $key => $label) {
                        // Handle nested relations with dot notation (e.g., 'user.name')
                        if (strpos($key, '.') !== false) {
                            $row[$label] = $this->getNestedValue($record, $key);
                        } else {
                            $row[$label] = $record->{$key};
                        }
                    }

                    // Write the row to CSV
                    fputcsv($file, $row);
                }
            });

            // Close the file
            fclose($file);
        }, 200, $headers);
    }

    /**
     * Get a nested value from an object using dot notation
     *
     * @param object $record The record to extract value from
     * @param string $key The key in dot notation (e.g., 'user.name')
     * @return mixed The value or null if not found
     */
    protected function getNestedValue($record, $key)
    {
        $parts = explode('.', $key);
        $value = $record;

        foreach ($parts as $part) {
            if (is_object($value) && isset($value->{$part})) {
                $value = $value->{$part};
            } elseif (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }

        return $value;
    }
}
