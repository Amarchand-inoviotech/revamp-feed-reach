<?php
namespace App\Traits;

use App\Enum\UserGaurdEnum;
use App\Helpers\FileUploader;
use App\Jobs\PasswordResetJob;
use App\Jobs\SendOtpJob;
use App\Models\OtpToken;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

trait AuthTrait
{
    protected function credentials(array $payload)
    {
        $username = $payload['username'] ?? '';
        $password = $payload['password'] ?? '';

        // Check if the input is an email or a username
        if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $username, 'password' => $password];
        } else {
            return ['username' => $username, 'password' => $password];
        }
    }

    protected function createToken($user, $userAgent)
    {
        return $user->createToken($userAgent, ['guard:' . $user->getGuardName()])->plainTextToken;
    }

    public function login(array $payload,$userAgent = "access-token")
    {
        $guard = $this->getGuardName();
        // $auth = Auth::guard($guard);
        $credentials = $this->credentials($payload);
        $rememberMe= $payload['remember'] ?? false;
        if (Auth::attempt($credentials, $rememberMe)) {
            $user = Auth::user();   

            // Load theme settings, avatar, and company relationships
            $user->load(['avatar', 'company','roles','permissions']);

            $token = $user->createToken($userAgent)->plainTextToken;
            $permissions = $this->getAllPermissions($user);

            return  $this->resource::make($user)->additional(['token' => $token, 'permissions' => $permissions]);

        } else {
            throw new Exception(trans('auth.failed'),401);
        }
    }

    public function getAllPermissions($user)
    {
        return Cache::remember("user_permissions_{$user->id}", 60, function () use ($user) {
            $directPermissions = $user->permissions;
            $rolePermissions = $user->roles->flatMap->permissions;
            return $directPermissions->concat($rolePermissions)->unique('id')->values();
        });
    }

    public function getGuardName()
    {
        return UserGaurdEnum::USER->value;
    }

    public function profile()
    {
        $user = Auth::user();
        $user->load('avatar');
        $devices = $user->tokens;
        return  $this->resource::make($user)->additional(['devices' => $devices]);
    }


    public function logout()
    {
        Auth::logout();
        return successResponse([], trans('auth.logout'));
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

    public function updateModel(Model $model, array $payload, bool $isResource = true)
    {
        $model->toFill($payload, ['avatar','permissions']);
        if (isset($payload['password'])) {
            $model->password = $payload['password'];
        }
        //uploading avatar image
        if (isset($payload['avatar']) && $payload['avatar'] instanceof \Illuminate\Http\UploadedFile) {
            $model->avatar_id = FileUploader::uploadFile($payload['avatar'], $model, 'avatars', oldAttachment: $model->avatar_id, size: AVATAR_SIZE)?->id;
        }

        $model->save();
        $model->load('avatar');
        return $this->resource && $isResource ? $this->resource::make($model) : $model;
    }

}
