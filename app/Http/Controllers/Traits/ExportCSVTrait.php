<?php

namespace App\Http\Controllers\Traits;

use App\DTOs\RequestParams;
use App\Http\Requests\IndexMethodRequest;
use Exception;
use Illuminate\Http\Response;

/**
 * Trait for controllers to handle CSV exports
 * Uses the same filters as the index method but without pagination
 */
trait ExportCSVTrait
{
    /**
     * Export data to CSV with the same filters as index but without pagination
     *
     * @param IndexMethodRequest $request
     * @param array $columns Associative array where keys are column names and values are display names
     * @return Response
     */
    public function exportCsv(IndexMethodRequest $request)
    {
        try {
           $columns= $this->getExportColumns();
            // Create RequestParams from the validated request
            $params = RequestParams::fromValidatedRequest($request->validated());

            // Use the exportCsv method which applies the same filters as getAll
            return $this->repo->exportCsv($params, $columns);
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get default export columns for a model
     * Override this method in your controller to customize columns
     *
     * @return array
     */
    protected function getExportColumns(): array
    {
        // Default columns that most models have
        return [
            'id' => 'ID',
            'name' => 'Name',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
        ];
    }

}
