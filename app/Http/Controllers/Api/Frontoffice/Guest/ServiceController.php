<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMethodRequest;
use App\Repositories\Contracts\ServiceRepositoryContract;
use Illuminate\Http\Request;

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

}
