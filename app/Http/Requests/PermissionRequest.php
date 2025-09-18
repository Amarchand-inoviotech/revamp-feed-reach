<?php

namespace App\Http\Requests;

use App\Enum\UserGaurdEnum;
use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PermissionRequest extends FormRequest
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
            'permissions' => 'required|array',
            'permissions.*' => 'required|exists:permissions,id',
        ];
    }

    public function prepareForValidation()
    {
        $guardName = $this->route('admin') ? UserGaurdEnum::ADMIN->value : UserGaurdEnum::USER->value;
        if ($this->has('permissions')) {
            $this->merge(['permissions' =>  Permission::whereIn('uuid', $this->permissions)
                ->where('guard_name', $guardName)
                ->pluck('id')->toArray()]);
        }
    }
}
