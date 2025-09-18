<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\StateRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\State;
use App\Repositories\Contracts\StateRepositoryContract;
use Exception;

class StateController extends Controller
{
    public function __construct(
        protected StateRepositoryContract $repo,
        protected string $model = 'State'
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
    public function store(StateRequest $request)
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
    public function show(State $state)
    {
        $response = $this->repo->showModel($state);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StateRequest $request, State $state)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($state, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(State $state)
    {
        try {
            $response = $this->repo->softDeleteModel($state);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}