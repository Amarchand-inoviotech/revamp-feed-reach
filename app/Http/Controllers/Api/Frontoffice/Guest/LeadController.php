<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestLeadRequest;
use App\Repositories\Contracts\LeadRepositoryContract;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        protected LeadRepositoryContract $repo,
        protected string $model = 'Lead'
    ) {}

    public function store(GuestLeadRequest $request)
    {
        $payload = $request->validated();
        try {
            $response = $this->repo->storeModel($payload);
            return successResponse($response, trans('generic.store', ['model' => $this->model]));
        } catch (\Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }
}
