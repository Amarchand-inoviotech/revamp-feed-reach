<?php

namespace App\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use App\DTOs\RequestParams;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class QueryBuilder
{
    protected Builder $query;
    protected RequestParams $params;

    public function __construct(Builder $query, RequestParams $params)
    {
        $this->query = $query;
        $this->params = $params;
    }

    public function apply(): Builder
    {
        return $this
            ->applyRelations()
            ->applyConditions()
            ->applyFilters()
            ->applyDateFilters()
            ->applyTrashedFilter()
            ->applySearch()
            ->applySorting()
            ->getQuery();
    }

    protected function applyRelations(): self
    {
        if (!empty($this->params->relations)) {
            $this->query->with($this->params->relations);
        }
        return $this;
    }

    protected function applyConditions(): self
    {
        if (!empty($this->params->conditions)) {
            foreach ($this->params->conditions as $field => $value) {
                if (is_array($value)) {
                    $this->query->whereIn($field, $value);
                } else {
                    $this->query->where($field, $value);
                }
            }
        }
        return $this;
    }

    protected function applyFilters(): self
    {
        if (!empty($this->params->filters)) {
            $model = $this->query->getModel();
            $modelColumns = $this->getModelColumns($model);

            foreach ($this->params->filters as $field => $value) {
                if ($value !== null) {
                    // Check if the field exists in the model columns
                    if (in_array($field, $modelColumns)) {
                        // Direct column filter
                        if (is_array($value)) {
                            $this->query->whereIn($field, $value);
                        } else {
                            $this->query->where($field, $value);
                        }
                    } else {
                        // Check if it's a relation filter (e.g., company_id, role_id)
                        $this->applyRelationFilter($field, $value);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Get model columns efficiently without making a database query
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return array
     */
    protected function getModelColumns($model): array
    {
        // First check if model has fillable property
        if (property_exists($model, 'fillable') && !empty($model->getFillable())) {
            return $model->getFillable();
        }

        // Then check if model has getColumns method (from ModelTrait)
        if (method_exists($model, 'getColumns')) {
            return $model->getColumns();
        }

        // As a last resort, use Schema (but this should rarely happen with proper models)
        return Schema::getColumnListing($model->getTable());
    }

    /**
     * Apply filter for relation fields
     *
     * @param string $field The filter field (e.g., company_id, role_id)
     * @param mixed $value The filter value
     * @return void
     */
    protected function applyRelationFilter(string $field, $value): void
    {
        // Check if field ends with _id which indicates a potential relation
        if (Str::endsWith($field, '_id')) {
            // Extract relation name (e.g., 'company' from 'company_id')
            $relationName = Str::beforeLast($field, '_id');

            // Check if the relation method exists on the model
            $model = $this->query->getModel();

            // Process values to handle UUIDs if needed
            $processedValues = $this->processFilterValues($value, $relationName);

            if (method_exists($model, $relationName)) {
                // Singular relation exists (belongsTo, hasOne, etc.)
                if (is_array($processedValues)) {
                    $this->query->whereHas($relationName, function ($query) use ($processedValues) {
                        $query->whereIn('id', $processedValues);
                    });
                } else {
                    $this->query->whereHas($relationName, function ($query) use ($processedValues) {
                        $query->where('id', $processedValues);
                    });
                }
            } elseif (method_exists($model, Str::plural($relationName))) {
                // Plural relation exists (hasMany, belongsToMany, etc.)
                $pluralRelation = Str::plural($relationName);

                if (is_array($processedValues)) {
                    $this->query->whereHas($pluralRelation, function ($query) use ($processedValues) {
                        $query->whereIn('id', $processedValues);
                    });
                } else {
                    $this->query->whereHas($pluralRelation, function ($query) use ($processedValues) {
                        $query->where('id', $processedValues);
                    });
                }
            } else {
                // If no relation found, try to apply the filter directly
                // This might happen if the field is a custom field not in the schema
                if (is_array($value)) {
                    $this->query->whereIn($field, $value);
                } else {
                    $this->query->where($field, $value);
                }
            }
        } else {
            // For non _id fields, try to find a matching relation
            if (method_exists($this->query->getModel(), $field)) {
                // Direct relation name match
                // Process values to handle UUIDs if needed
                $processedValues = $this->processFilterValues($value, $field);

                if (is_array($processedValues)) {
                    $this->query->whereHas($field, function ($query) use ($processedValues) {
                        $query->whereIn('id', $processedValues);
                    });
                } else {
                    $this->query->whereHas($field, function ($query) use ($processedValues) {
                        $query->where('id', $processedValues);
                    });
                }
            } else {
                // Default fallback - apply filter directly
                if (is_array($value)) {
                    $this->query->whereIn($field, $value);
                } else {
                    $this->query->where($field, $value);
                }
            }
        }
    }

    /**
     * Process filter values to handle UUIDs if needed
     *
     * @param mixed $value The filter value(s)
     * @param string $relationName The name of the relation
     * @return mixed Processed value(s) (converted from UUID to ID if needed)
     */
    protected function processFilterValues($value, string $relationName): mixed
    {
        // Get the related model class first
        $relatedModel = $this->getRelatedModelClass($relationName);
        if (!$relatedModel) {
            return $value; // Can't determine related model, return original values
        }

        // Handle array of values
        if (is_array($value)) {
            return $this->processArrayValues($value, $relatedModel);
        }

        // Handle single value
        return $this->processSingleValue($value, $relatedModel);
    }

    /**
     * Process an array of filter values
     *
     * @param array $values The array of values to process
     * @param string $relatedModel The related model class
     * @return array The processed values
     */
    protected function processArrayValues(array $values, string $relatedModel): array
    {
        // Check if any values look like UUIDs
        $uuidValues = [];
        $nonUuidValues = [];

        foreach ($values as $item) {
            if (is_string($item) && $this->isUuid($item)) {
                $uuidValues[] = $item;
            } else {
                $nonUuidValues[] = $item;
            }
        }

        // If no UUIDs found, return original values
        if (empty($uuidValues)) {
            return $values;
        }

        // Convert UUIDs to IDs in a single query for better performance
        $idMap = $relatedModel::whereIn('uuid', $uuidValues)
            ->pluck('id', 'uuid')
            ->toArray();

        // Replace UUIDs with their corresponding IDs
        $result = $nonUuidValues;
        foreach ($uuidValues as $uuid) {
            if (isset($idMap[$uuid])) {
                $result[] = $idMap[$uuid];
            }
        }

        return $result;
    }

    /**
     * Process a single filter value
     *
     * @param mixed $value The value to process
     * @param string $relatedModel The related model class
     * @return mixed The processed value
     */
    protected function processSingleValue($value, string $relatedModel)
    {
        // Check if value is a UUID
        if (is_string($value) && $this->isUuid($value)) {
            // Find the ID for this UUID
            $model = $relatedModel::where('uuid', $value)->first();
            return $model ? $model->id : $value;
        }

        // Not a UUID, return as is
        return $value;
    }

    /**
     * Check if a string is a valid UUID
     *
     * @param string $value The string to check
     * @return bool True if the string is a UUID, false otherwise
     */
    protected function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Get the related model class based on relation name
     *
     * @param string $relationName The name of the relation
     * @return string|null The related model class or null if not found
     */
    protected function getRelatedModelClass(string $relationName): ?string
    {
        // Try to determine the related model class
        $model = $this->query->getModel();

        // Check if the singular relation method exists
        if (method_exists($model, $relationName)) {
            $relationMethod = $relationName;
        }
        // Check if the plural relation method exists
        elseif (method_exists($model, Str::plural($relationName))) {
            $relationMethod = Str::plural($relationName);
        }
        // No relation method found
        else {
            return null;
        }

        // Try to get the related model from the relation
        try {
            $relation = $model->{$relationMethod}();
            return get_class($relation->getRelated());
        } catch (\Exception) {
            return null;
        }
    }

    protected function applyDateFilters(): self
    {
        if ($this->params->dateFrom) {
            $this->query->where($this->params->dateField, '>=', $this->params->dateFrom);
        }

        if ($this->params->dateTo) {
            $this->query->where($this->params->dateField, '<=', $this->params->dateTo);
        }
        return $this;
    }

    protected function applyTrashedFilter(): self
    {
        if (method_exists($this->query->getModel(), 'trashed')) {
            if ($this->params->trashed === RequestParams::TRASHED_WITH) {
                $this->query->withTrashed();
            } elseif ($this->params->trashed === RequestParams::TRASHED_ONLY) {
                $this->query->onlyTrashed();
            }
        }
        return $this;
    }

    protected function applySearch(): self
    {
        if ($this->params->search && $this->params->searchCallback) {
            $this->query->where(function ($query) {
                call_user_func($this->params->searchCallback, $query, $this->params->search);
            });
        }
        return $this;
    }

    protected function applySorting(): self
    {
        $this->query->orderBy($this->params->orderBy, $this->params->orderDirection);
        return $this;
    }

    protected function getQuery(): Builder
    {
        return $this->query;
    }
}
