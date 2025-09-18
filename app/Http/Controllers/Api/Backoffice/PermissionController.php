<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Permission;
use App\Repositories\Contracts\PermissionRepositoryContract;
use Exception;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    use ExportCSVTrait;
    public function __construct(
        private PermissionRepositoryContract $repo,
        private string $model = 'Permission'
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        $validated = $request->validated();

        $params = RequestParams::fromValidatedRequest($validated);
        $response = $this->repo->getAll($params);

        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }

    /**
     * Restore the specified resource.
     */
    public function restore(Permission $permission)
    {
        try {
            $response = $this->repo->restoreModel($permission);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(Permission $permission)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($permission);
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
            'guard_name'=> 'Guard Name',
            'created_at' => 'Created Date',
        ];
    }
}
