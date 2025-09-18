<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\IndexMethodRequest;
use App\Http\Requests\PermissionRequest;
use App\Http\Requests\Frontoffice\UserRegisterRequest;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use ExportCSVTrait;
    public function __construct(
        private readonly UserRepositoryContract $repo,
        private string $model = "User"
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        // No need to specify relations as they're defined in the repository
        $params = RequestParams::fromValidatedRequest($request->validated());
        $response = $this->repo->getAll($params);
        return successResponse($response,  trans('generic.index', ['model' => $this->model]), paginate: $params->paginate);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserRegisterRequest $request)
    {
        $payload = $request->validated();
        try {
            $response = null;
            DB::transaction(function () use (&$response, $payload) {
                $response = $this->repo->storeModel($payload);
            });
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }

        return successResponse($response, trans('generic.store', ['model' => $this->model]));
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        $user->load('avatar', 'company');
        $response = $this->repo->showModel($user);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UserRegisterRequest $request, User $user)
    {
        $payload = $request->validated();
        try {
            $response = null;
            DB::transaction(function () use (&$response, $user, $payload) {
                $response = $this->repo->updateModel($user, $payload);
            });
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }

        return successResponse($response, trans('generic.update', ['model' => $this->model]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        try {
            $response = $this->repo->softDeleteModel($user);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }


    public function syncDirectPermissions(PermissionRequest  $request, User $user)
    {
        $payload = $request->validated();

        try {
            $user->syncPermissions($payload['permissions']);
            return successResponse(null, trans('user.sync-direct-permission', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore the specified resource.
     */
    public function restore(User $user)
    {
        try {
            $response = $this->repo->restoreModel($user);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(User $user)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($user);
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
            'first_name' => 'First Name',
            'last_name'=> 'last_name',
            'email' => 'Email',
            'phone' => 'Phone',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
            // Add relation columns with dot notation if needed
            // 'company.name' => 'Company Name',
            // 'roles.name' => 'Role',
        ];
    }
}
