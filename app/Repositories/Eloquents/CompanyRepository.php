<?php

namespace App\Repositories\Eloquents;

use App\Helpers\FileUploader;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryContract;
use Illuminate\Database\Eloquent\Model;

class CompanyRepository extends BaseRepository implements CompanyRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = ['defaultCurrency', 'logo'];

    public function __construct(Company $model)
    {
        parent::__construct($model, CompanyResource::class);
    }

    /**
     * Get search callback for company model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        };
    }



    public function storeModel(array $payload, bool $isResource = true)
    {
        $model = $this->model;
        $model->toFill($payload, ['logo']);
        $model->save();
        //uploading avatar image
        if (isset($payload['logo']) && $payload['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $model->logo_id = FileUploader::uploadFile($payload['logo'], $model, 'company_logo', size: 64)?->id;
        }

        $model->save();
        return $this->resource ? $this->resource::make($model) : $model;
    }

    public function updateModel(Model $model, array $payload, bool $isResource = true)
    {
        $model->toFill($payload, ['logo']);
        $model->save();
        //uploading avatar image
        if (isset($payload['logo']) && $payload['logo'] instanceof \Illuminate\Http\UploadedFile) {
            $model->logo_id = FileUploader::uploadFile($payload['logo'], $model, 'company_logo', oldAttachment: $model->logo_id, size: 64)?->id;
        }

        $model->save();
        return $this->resource ? $this->resource::make($model) : $model;
    }
}
