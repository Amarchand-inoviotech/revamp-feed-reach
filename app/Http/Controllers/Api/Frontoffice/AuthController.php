<?php

namespace App\Http\Controllers\Api\Frontoffice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontoffice\UserLoginRequest;
use App\Http\Requests\Frontoffice\UserRegisterRequest;
use App\Models\User;
use App\Repositories\Contracts\Auth\UserRepositoryContract;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{

    public function __construct(
        private readonly UserRepositoryContract $repo,
        private string $model = 'User'
    ) {}
    public function login(UserLoginRequest $request)
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


    public function create(UserRegisterRequest $request)
    {
        $payload = $request->validated();
        try {
            $model = new User;
            $response = null;
            DB::transaction(function () use (&$model, $payload,&$response) {
               $response = $this->repo->updateModel($model,$payload);
            });
        } catch (Exception $e) {
            return errorResponse($e->getMessage(), 500);
        }

        return successResponse($response, trans('auth.user_created'));
    }

    public function profile()
    {
        try{
            $response = $this->repo->profile();
            return successResponse( $response , trans('auth.profile_fetch'));
        }catch(Exception $e){
              return errorResponse($e->getMessage(),$e->getCode());
        }
    }

    public function updateProfile(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'phone' => 'required|string|unique:users,phone,' . $request->user()->id,
        ]);
        $user = $request->user();
        $user->toFill($payload);
        $user->save();

    }

    public function revokeToken(Request $request, $token)
    {
        $request->user()->tokens()->where('id', $token)->delete();
        return successResponse([], trans('auth.token_revoked'));
    }
}
