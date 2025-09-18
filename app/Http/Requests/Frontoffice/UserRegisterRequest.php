<?php

namespace App\Http\Requests\Frontoffice;

use App\Enum\DevicePlatformEnum;
use App\Enum\GenderEnum;
use App\Models\Company;
use App\Rules\UsernameBlockedRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UserRegisterRequest extends FormRequest
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

        $rules = [

            'name'    => ['required', 'string', $min_3 = 'min:3'],
            'email'         => ['required', 'email',  Rule::unique('users', 'email')->ignore(request()->route('user'))],
            'username'       => ['required', 'string', 'min:3', Rule::unique('users', 'username')->ignore(request()->route('user'))],
            'phone'         => [
                'required',
                'string',
                'regex:/^(\+\d{1,2}\s?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/i',
                Rule::unique('users', 'phone')->ignore(request()->route('user'))
            ],
            'gender'        => ['required', Rule::in(GenderEnum::cases())],
            'dob'           => ['required', 'date', 'before:today'],
            'avatar'        => ['nullable', 'image'],
            'password'      => ['required', 'confirmed', 'min:6'],
            'company_id'    => ['required', 'exists:companies,id'],

            'device'                => ['sometimes', 'array'],
            'device.udid'           => ['required_with:device', 'string', 'min:1'],
            'device.platform'       => ['required_with:device', 'string', new Enum(DevicePlatformEnum::class)],
            'device.token'          => ['required_with:device', 'string', 'min:1'],

        ];
        if (request()->route('user')) {
            $rules['password'] = ['nullable', 'confirmed', 'min:6'];
        }
        return $rules;
    }

    public function prepareForValidation()
    {
        if ($this->has('company_id')) {
            $this->merge(['company_id' =>  Company::where('uuid', $this->company_id)->value('id')]);
        }
    }
}
