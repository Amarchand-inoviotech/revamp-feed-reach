<?php

namespace App\Http\Controllers\Api\Backoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Repositories\Contracts\Auth\UserRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserRepositoryContract $repo
    ) {}

      public function login(AuthLoginRequest $request)
    {
        $payload = $request->validated();
        try{
            $response = $this->repo->login($payload);
            return successResponse( $response , trans('auth.loggedin'));
        }catch(Exception $e){
              return errorResponse($e->getMessage(),$e->getCode());
        }
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return successResponse([], trans('auth.logout'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $user->load(['roles.permissions', 'permissions']);

        // Load all permissions (direct and via roles) with caching
        $permissions = Cache::remember("user_permissions_{$user->id}", 60, function () use ($user) {
            $directPermissions = $user->permissions;
            $rolePermissions = $user->roles->flatMap->permissions;
            return $directPermissions->concat($rolePermissions)->unique('id')->values();
        });

        // Pass permissions to the repository's showModel method
        $response = $this->repo->showModel($user, [], true, $permissions);
        return successResponse($response, trans('auth.profile_fetch'));
    }

    public function updateProfile(Request $request)
    {
        $payload = $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'phone' => 'required|string|unique:users,phone,' . $request->user()->id,
        ]);
        $user = $request->user();
        $user->toFill($payload);
        $user->save();
        return successResponse($user, trans('auth.profile_updated'));
    }

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

    public function forgotPassword(Request $request)
    {

        $payload = $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string'
        ]);
        $this->repo->forgotPassword($payload);
        $message = "Check your " . ucfirst($payload['type']) . ". We sent a message if it was found.";
        return successResponse([], $message, 200);
    }

    public function resetPassword(Request $request)
    {

        $payload = $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
            'otp' => 'required|string'
        ]);
        try {
            $this->repo->resetPassword($payload);
        } catch (Exception $e) {
            return errorResponse(trans('auth.otp_wrong'), 500);
        }
        return successResponse([], trans('auth.password_reset'), 200);
    }
}
