<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryContract;
use Exception;

class CompanyController extends Controller
{
    private CompanyRepositoryContract $repo;

    /**
     * Display a listing of the resource.
     */

    public function __construct(CompanyRepositoryContract $repository)
    {
        $this->repo = $repository;
    }

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

        return successResponse($response, trans('company.index'), 200, $params->paginate);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CompanyRequest $request)
    {
        $payload = $request->validated();
        $response = null;
        try {
            $response = $this->repo->storeModel($payload);
            return successResponse($response, trans('company.store'));
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $response = $this->repo->showModel(
            $company
        );
        return successResponse($response, trans('company.show'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CompanyRequest $request, Company $company)
    {
        $payload = $request->validated();

        try {
            $response = $this->repo->updateModel($company, $payload, false);
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }

        return successResponse($response, trans('company.show'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {

        try {
            $response = [];
            $response = $this->repo->permanentlyDeleteModel($company);
            return  successResponse($response, trans('company.destroy'));
        } catch (Exception $e) {
            return errorResponse($e->getMessage());
        }
    }
}
