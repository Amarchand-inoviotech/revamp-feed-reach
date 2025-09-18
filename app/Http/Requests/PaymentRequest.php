<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class PaymentRequest extends FormRequest
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
            'invoice_id'        => ['required', 'exists:invoices,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'type_id'        => ['required', 'exists:payment_types,id'],
            'transaction_id'    => ['nullable', 'string', 'max:255'],
            'content'        => ['required', 'string'],
            'payment_status'    => ['required', 'string', 'in:pending,completed,failed'],
            'type'              => ['required', 'string', 'in:invoice,receipt,quote'],
            'response'          => ['nullable', 'string'],
            'client_ip'         => ['nullable', 'string'],
            'cc_type'           => ['nullable', 'string'],
            'status'            => ['nullable', 'string']
        ];
    }
}
