<?php

namespace App\Repositories\Eloquents\Auth;

use App\Http\Resources\AuthResource;
use App\Models\User;
use App\Repositories\Contracts\Auth\UserRepositoryContract;
use App\Repositories\Eloquents\BaseRepository;
use App\Traits\AuthTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    use AuthTrait;
    /**
     * Default relations to load
     */
    protected array $defaultRelations = ['avatar'];

    public function __construct(User $model)
    {
        $this->model = $model;
        $this->resource = AuthResource::class;
    }

    /**
     * Get search callback for admin model
     *
     * @return \Closure
     */
    public function getSearchCallback(): \Closure
    {
        return function ($query, $search) {
            $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%");
        };
    }

    public function showModel(Model $model, array $relations = [], bool $isResource = true)
    {
        $model = parent::showModel($model, $relations, false);
        return $this->resource ? $this->resource::make($model) : $model;
    }

}
