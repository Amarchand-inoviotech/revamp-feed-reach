<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryContract;
use Exception;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    use ExportCSVTrait;
    public function __construct(
        private CurrencyRepositoryContract $repo,
        private string $model = 'Currency'
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        $params = RequestParams::fromValidatedRequest($request->validated());
        $response = $this->repo->getAll($params);
        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }

    /**
     * Restore the specified resource.
     */
    public function restore(Currency $currency)
    {
        try {
            $response = $this->repo->restoreModel($currency);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(Currency $currency)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($currency);
            return successResponse($response,  trans('generic.permanently_destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function bulkDestroy(Request $request){
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|string|uuid',
        ]);
        try{
            $this->repo->bulkDeleteModel($request->ids);
            return successResponse(null, trans('generic.bulk_destroy', ['model' => $this->model]));
        }catch(Exception $e){
            return errorResponse($e->getMessage(), 500);
        }
    }

         /**
     * Get export columns for User model
     *
     * @return array
     */
    protected function getExportColumns(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'symbol'=> 'Symbol',
            'created_at' => 'Created Date',
        ];
    }
}
