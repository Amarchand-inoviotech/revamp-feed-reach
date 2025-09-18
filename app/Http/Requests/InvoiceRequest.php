<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'exists:accounts,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'content' => ['required', 'string'],
            'addr' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'payment_url' => ['nullable', 'string', 'max:255'],
            'amount'  => ['required', 'numeric'],
            'contract' => ['nullable', 'string', 'max:255'],
            'es_template_uuid' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:invoice,receipt,quote'],
        ];
    }
}
