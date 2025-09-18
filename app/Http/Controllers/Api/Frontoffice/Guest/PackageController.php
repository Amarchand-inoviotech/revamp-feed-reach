<?php

namespace App\Http\Controllers\Api\Frontoffice\Guest;

use App\DTOs\RequestParams;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMethodRequest;
use App\Repositories\Contracts\PackageRepositoryContract;
use Illuminate\Http\Request;

class PackageController extends Controller
{

     public function __construct(
        protected PackageRepositoryContract $repo,
        protected string $model = 'Package'
    ) {}

      /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $response = $this->repo->getAllWithAttributes();

        return successResponse($response, trans('generic.index', ['model' => $this->model]), 200, 0);
    }

}
