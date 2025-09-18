<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
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
            "name" => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($this->route('role'))],
            "guard_name" => ['required', 'string', 'max:255'],
            "permissions" => ['nullable', 'array'],
            "permissions.*" => ['required', 'exists:permissions,id'],
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('permissions')) {
            $this->merge(['permissions' =>  Permission::whereIn('uuid', $this->permissions)
                ->where('guard_name', $this->guard_name)->pluck('id')->toArray()]);
        }
    }
}
