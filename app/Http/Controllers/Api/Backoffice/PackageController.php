<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\PackageRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function __construct(
        protected PackageRepositoryContract $repo,
        protected string $model = 'Package'
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        $validated = $request->validated();

        // You can add specific filters here if needed
        // Example: Add status filter if provided in the request
        if ($request->has('status')) {
            $validated['filters'] = $validated['filters'] ?? [];
            $validated['filters']['status'] = $request->input('status');
        }

        $params = RequestParams::fromValidatedRequest($validated);
        $response = $this->repo->getAll($params);

        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PackageRequest $request)
    {
        $payload = $request->validated();
        try {
            DB::transaction(function () use (&$response, $payload) {
                $response = $this->repo->storeModel($payload);
            });
            return successResponse($response, trans('generic.store', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Package $package)
    {
        $response = $this->repo->showModel($package);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PackageRequest $request, Package $package)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($package, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package)
    {
        try {
            $response = $this->repo->softDeleteModel($package);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore the specified resource.
     */
    public function restore(Package $package)
    {
        try {
            $response = $this->repo->restoreModel($package);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Register gateway package for a specific package
     */
    public function registerGatewayPackage(Request $request, Package $package)
    {
        $payload = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,uuid'],
        ]);

        try {
            $response = $this->repo->registerGatewayPackages($package, $payload['payment_method_id']);
            return successResponse($response, trans('package.register-gateway-packages'));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Force recreate gateway plan for a specific package (delete + create)
     */
    public function recreateGatewayPlan(Request $request, Package $package)
    {
        $payload = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,uuid'],
        ]);

        try {
            $response = $this->repo->recreateGatewayPlan($package, $payload['payment_method_id']);
            return successResponse($response, 'Gateway plan recreated successfully');
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
