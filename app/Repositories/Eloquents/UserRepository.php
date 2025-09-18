<?php

namespace App\Repositories\Eloquents;

use App\Helpers\FileUploader;
use App\Http\Resources\UserResource;
use App\Jobs\PasswordResetJob;
use App\Jobs\SendOtpJob;
use App\Models\OtpToken;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserRepository extends BaseRepository implements UserRepositoryContract
{
    /**
     * Default relations to load
     */
    protected array $defaultRelations = ['avatar', 'company'];

    public function __construct(User $model)
    {
        parent::__construct($model, UserRepositoryContract::class);
    }


    /**
     * Get search callback for user model
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



    public function storeModel(array $payload, bool $isResource = true)
    {
        $model = $this->model;
        $model->toFill($payload, ['avatar']);
        $model->password = $payload['password'];

        $model->save();
        //uploading avatar image
        if (isset($payload['avatar']) && $payload['avatar'] instanceof \Illuminate\Http\UploadedFile) {
            $model->avatar_id = FileUploader::uploadFile($payload['avatar'], $model, 'avatars', size: 64)?->id;
        }

        $model->save();
        return $this->resource && $isResource? $this->resource::make($model) : $model;
    }
    public function updateModel(Model $model, array $payload, bool $isResource = true)
    {
        $model->toFill($payload, ['avatar', 'password']);

        if (isset($payload['password'])) {
            $model->password = $payload['password'];
        }
        $model->save();
        //uploading avatar image
        if (isset($payload['avatar']) && $payload['avatar'] instanceof \Illuminate\Http\UploadedFile) {
            $model->avatar_id = FileUploader::uploadFile($payload['avatar'], $model, 'avatars', oldAttachment: $model->avatar_id, size: 64)?->id;
        }

        $model->save();
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

    public function forgotPassword(array $payload): void
    {
        $model = $this->model->where($payload['type'], $payload['value'])->first();
        if ($model) {
            $otpToken = $model->otpTokens()
                ->where('expires_at', '>=', now())
                ->firstOrCreate(
                    [
                        'type' => $payload['type']
                    ],
                    [
                        'token' => generateOtp(),
                        'expires_at' => now()->addMinutes(15),
                    ]
                );
            if ($otpToken->type == 'email') {
                $otpToken->load('model');
                SendOtpJob::dispatch($otpToken);
            }
        }
    }

    public function resetPassword(array $payload): void
    {
        $otpToken = OtpToken::where('token', $payload['otp'])->where('type', $payload['type'])->where('expires_at', '>=', now())->firstOrFail();
        $otpToken->model()->update([
            'password' => bcrypt($payload['password'])
        ]);
        if ($otpToken->type == 'email') {
            $otpToken->load('model');
            PasswordResetJob::dispatch($otpToken->model);
        }
        $otpToken->delete();
    }
}
