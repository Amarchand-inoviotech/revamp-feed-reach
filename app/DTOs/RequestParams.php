<?php

namespace App\DTOs;

class RequestParams
{
    /**
     * Trashed filter options
     */
    const TRASHED_NONE = 'without';
    const TRASHED_WITH = 'with';
    const TRASHED_ONLY = 'only';

    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $orderBy = 'id',
        public readonly ?string $orderDirection = 'desc',
        public bool $paginate = true,
        public readonly int $perPage = 15,
        public array $relations = [],
        public array $conditions = [],
        public ?\Closure $searchCallback = null,
        public array $filters = [],
        public ?string $trashed = self::TRASHED_NONE,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public readonly ?string $dateField = 'created_at'
    ) {}

    /**
     * Create a RequestParams instance from a validated request array
     *
     * @param array $validated
     * @param array $relations
     * @param array $conditions
     * @param \Closure|null $searchCallback Function to handle custom search logic
     * @return self
     */
    public static function fromValidatedRequest(
        array $validated,
        array $relations = [],
        array $conditions = [],
        ?\Closure $searchCallback = null
    ): self {
        // Extract filters from validated data
        $filters = $validated['filters'] ?? [];

        // Get trashed option
        $trashed = $validated['trashed'] ?? self::TRASHED_NONE;
        if (!in_array($trashed, [self::TRASHED_NONE, self::TRASHED_WITH, self::TRASHED_ONLY])) {
            $trashed = self::TRASHED_NONE;
        }

        // Get date filters
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $dateField = $validated['date_field'] ?? 'created_at';

        return new self(
            search: $validated['search'] ?? null,
            orderBy: $validated['sort_by'] ?? 'id',
            orderDirection: $validated['sort_direction'] ?? 'desc',
            paginate: $validated['paginate'] ?? true,
            perPage: $validated['per_page'] ?? 15,
            relations: $relations,
            conditions: $conditions,
            searchCallback: $searchCallback,
            filters: $filters,
            trashed: $trashed,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            dateField: $dateField
        );
    }
}
