<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ExportCSVTrait;
use App\Http\Requests\CompanyRequest;
use App\Http\Requests\IndexMethodRequest;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    use ExportCSVTrait;

    public function __construct(private CompanyRepositoryContract $repo, private string $model = 'Company') {}

    /**
     * Display a listing of the resource.
     */
    public function index(IndexMethodRequest $request)
    {
        // No need to specify relations as the repository handles default relations
        $params = RequestParams::fromValidatedRequest($request->validated());
        $response = $this->repo->getAll($params);
        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, $params->paginate);
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(CompanyRequest $request)
    {
        $payload = $request->validated();
        try {
            $response = null;
            DB::transaction(function () use ($payload, &$response) {
                $response = $this->repo->storeModel($payload);
            });
            return successResponse($response, trans('generic.store', ['model' => $this->model]));
        } catch (\Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $company->load(['defaultCurrency', 'logo']);
        $response = $this->repo->showModel($company);
        return successResponse($response, trans('generic.show', ['model' => $this->model]));
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(CompanyRequest $request, Company $company)
    {
        $payload = $request->validated();
        try {
            $response = null;
            DB::transaction(function () use (&$response, $company, $payload) {
                $response = $this->repo->updateModel($company, $payload);
            });
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }

        return successResponse($response, trans('generic.update', ['model' => $this->model]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        try {
            $response = $this->repo->softDeleteModel($company);
            return successResponse($response, trans('generic.destroy', ['model' => $this->model]));
        } catch (\Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Restore the specified resource.
     */
    public function restore(Company $company)
    {
        try {
            $response = $this->repo->restoreModel($company);
            return successResponse($response, trans('generic.restore', ['model' => $this->model]));
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource permanently.
     */
    public function permanentlyDestroy(Company $company)
    {
        try {
            $response = $this->repo->permanentlyDeleteModel($company);
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
     * Get export columns for Company model
     *
     * @return array
     */
    protected function getExportColumns(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Company Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'address' => 'Address',
            'domain' => 'Website',
            'created_at' => 'Created Date',
        ];
    }
}
