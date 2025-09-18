<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\IndexMethodRequest;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryContract;
use Exception;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use ExportCSVTrait;
    public function __construct(
        private RoleRepositoryContract $repo,
        private string $model = 'Role'
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
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
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
    public function show(Role $role)
    {
        $role->load('permissions');
        $response = $this->repo->showModel($role);
        return successResponse($response,  trans('generic.show', ['model' => $this->model]));
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($role, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        try {
            $response = $this->repo->softDeleteModel($role);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    public function restore(Role $role)
    {
        try {
            $response = $this->repo->restoreModel($role);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(Role $role)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($role);
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
            'guard_name'=> 'Guard Name',
            'created_at' => 'Created Date',
        ];
    }
}
