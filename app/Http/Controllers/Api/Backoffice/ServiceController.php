<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Service;
use App\Repositories\Contracts\ServiceRepositoryContract;
use Exception;

class ServiceController extends Controller
{
    public function __construct(
        protected ServiceRepositoryContract $repo,
        protected string $model = 'Service'
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
    public function store(ServiceRequest $request)
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
    public function show(Service $service)
    {
        $response = $this->repo->showModel($service);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ServiceRequest $request, Service $service)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($service, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        try {
            $response = $this->repo->softDeleteModel($service);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
