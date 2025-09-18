<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $package = $this->route('package');
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('packages')->ignore($package?->id)],
            'price' => ['required', 'integer'],
            'billing_cycle' => ['required', 'string', Rule::in(array_column(\App\Enum\BillingCycleEnum::cases(), 'value'))],
            'is_agent' => ['required', 'boolean'],
        ];
    }
}
