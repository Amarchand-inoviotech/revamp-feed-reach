<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionRequest extends FormRequest
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
            'billing_cycle' => ['required', 'string', 'in:daily,weekly,monthly,quarterly,bi-quarterly,yearly'],
            'user_id' => ['required', 'exists:users,uuid'],
            'gateway_package_id' => ['required', 'exists:gateway_packages,uuid'],
            'getway_subscription_id' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'next_billing_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'in:active,canceled,paused,expired'],
            'extra' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_cycle.in' => 'The billing cycle must be one of: daily, weekly, monthly, quarterly, bi-quarterly, yearly.',
            'user_id.exists' => 'The selected user does not exist.',
            'gateway_package_id.exists' => 'The selected gateway package does not exist.',
            'status.in' => 'The status must be one of: active, canceled, paused, expired.',
            'start_date.after_or_equal' => 'The start date must be today or a future date.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }
}
