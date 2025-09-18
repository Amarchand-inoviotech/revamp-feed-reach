<?php

namespace App\Http\Requests;

use App\Enum\LocaleEnum;
use App\Models\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CompanyRequest extends FormRequest
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
        return [
            'default_currency' => ['nullable', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'slug' => ['required', 'string', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'email' => ['required', 'email', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'invoice_url' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', new Enum(LocaleEnum::class)],
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('default_currency')) {
            $this->merge(['default_currency' =>  Currency::where('uuid', $this->default_currency)->value('id')]);
        }
    }
}
