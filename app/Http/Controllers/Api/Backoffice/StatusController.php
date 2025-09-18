<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\IndexMethodRequest;
use App\Http\Requests\StatusRequest;
use App\Models\Status;
use App\Repositories\Contracts\StatusRepositoryContract;
use Exception;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    use ExportCSVTrait;
    public function __construct(
        private StatusRepositoryContract $repo,
        private string $model = 'Status'
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        $params = RequestParams::fromValidatedRequest($request->validated());
        $response = $this->repo->getAll($params);
        return successResponse($response, trans('generic.index', ['model' => $this->model]), paginate: $params->paginate);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(StatusRequest $request)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->storeModel($payload);
            return successResponse($response, trans('generic.store', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Status $status)
    {
        $response = $this->repo->showModel($status);
        return successResponse($response,  trans('generic.show', ['model' => $this->model]));
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(StatusRequest $request, Status $status)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($status, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Status $status)
    {
        try {
            $response = $this->repo->softDeleteModel($status);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore the specified resource.
     */
    public function restore(Status $status)
    {
        try {
            $response = $this->repo->restoreModel($status);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(Status $status)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($status);
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

    /* Get export columns for User model
     *
     * @return array
     */
    protected function getExportColumns(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'model' => 'Model',
            'created_at' => 'Created Date',
        ];
    }
}
