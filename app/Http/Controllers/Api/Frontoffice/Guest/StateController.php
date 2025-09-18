<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMethodRequest;
use App\Repositories\Contracts\StateRepositoryContract;
use Illuminate\Http\Request;

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

        $params = RequestParams::fromValidatedRequest($validated);
        $response = $this->repo->getAll($params);

        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }
}
