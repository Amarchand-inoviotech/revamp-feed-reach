<?php

namespace App\Http\Requests\Auth;

use App\Enum\DevicePlatformEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AuthLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => 'required_if:otp_code,null|string:min:3',
            'password' => 'required_if:otp_code,null|min:6',
            'remember' => 'nullable|boolean',

            'device'                => ['sometimes', 'array'],
            'device.udid'           => ['required_with:device', 'string', 'min:1'],
            'device.platform'       => ['required_with:device', 'string', new Enum(DevicePlatformEnum::class)],
            'device.token'          => ['required_with:device', 'string', 'min:1'],
        ];
    }
}
