<?php

namespace App\Http\Requests;

use App\Models\Card;
use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SubscribeRequest extends FormRequest
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
            'package_id' => ['required','numeric'],
            // Customer vault support - use existing customer and card
            'customer_id' => ['nullable', 'numeric'],
            'card_id' => ['nullable', 'numeric'],
            // Card details only required when not using existing customer/card
            "card" => ['required_without_all:card_id,customer_id', 'array'],
            "card.number" => ['required_with:card', 'string', 'min:13', 'max:19', 'regex:/^[0-9]+$/'],
            "card.exp_month" => ['required_with:card', 'string', 'size:2', 'regex:/^(0[1-9]|1[0-2])$/'],
            "card.exp_year" => ['required_with:card', 'string', 'size:4', 'regex:/^[0-9]{4}$/', 'after_or_equal:' . date('Y')],
            "card.cvv" => ['required_with:card', 'string', 'min:3', 'max:4', 'regex:/^[0-9]+$/'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            // Billing info only required for new cards
            'billing_info' => ['nullable', 'array'],
            'billing_info.first_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.last_name' => ['required_with:card', 'string', 'max:100'],
            'billing_info.email' => ['nullable', 'email'],
            'billing_info.phone' => ['nullable', 'string', 'max:20'],
            'billing_info.address' => ['nullable', 'string', 'max:255'],
            'billing_info.city' => ['nullable', 'string', 'max:100'],
            'billing_info.state' => ['nullable', 'string', 'max:100'],
            'billing_info.zip' => ['nullable', 'string', 'max:20'],
            'billing_info.country' => ['nullable', 'string', 'max:2'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'package_id.required' => 'Package selection is required.',
            'package_id.numeric' => 'Invalid package selected.',
            'customer_id.numeric' => 'Invalid customer selected.',
            'card_id.numeric' => 'Invalid card selected.',
            'card.required_without_all' => 'Card information is required when no existing customer or card is selected.',
            'card.number.required_with' => 'Card number is required.',
            'card.number.regex' => 'Card number must contain only digits.',
            'card.number.min' => 'Card number must be at least 13 digits.',
            'card.number.max' => 'Card number must not exceed 19 digits.',
            'card.exp_month.required_with' => 'Expiration month is required.',
            'card.exp_month.regex' => 'Expiration month must be between 01 and 12.',
            'card.exp_year.required_with' => 'Expiration year is required.',
            'card.exp_year.regex' => 'Expiration year must be a 4-digit year.',
            'card.exp_year.after_or_equal' => 'Card expiration year cannot be in the past.',
            'card.cvv.required_with' => 'CVV is required.',
            'card.cvv.regex' => 'CVV must contain only digits.',
            'card.cvv.min' => 'CVV must be at least 3 digits.',
            'card.cvv.max' => 'CVV must not exceed 4 digits.',
            'billing_info.first_name.required_with' => 'First name is required when providing card information.',
            'billing_info.last_name.required_with' => 'Last name is required when providing card information.',
            'start_date.after_or_equal' => 'Start date must be today or a future date.',
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('package_id')) {
            $this->merge(['package_id' =>  Package::where('uuid', $this->package_id)->value('id')]);
        }

        if ($this->has('card_id')) {
            $this->merge(['card_id' =>  Card::where('uuid', $this->card_id)->value('id')]);
        }

        if ($this->has('customer_id')) {
            $this->merge(['customer_id' =>  Customer::where('uuid', $this->customer_id)->value('id')]);
        }
    }
}
