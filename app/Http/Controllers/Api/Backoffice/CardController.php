<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Card;
use App\Repositories\Contracts\CardRepositoryContract;
use Exception;

class CardController extends Controller
{
    public function __construct(
        protected CardRepositoryContract $repo,
        protected string $model = 'Card'
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
    public function store(CardRequest $request)
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
    public function show(Card $card)
    {
        $response = $this->repo->showModel($card);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CardRequest $request, Card $card)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->updateModel($card, $payload);
            return successResponse($response, trans('generic.update', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Card $card)
    {
        try {
            $response = $this->repo->softDeleteModel($card);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}