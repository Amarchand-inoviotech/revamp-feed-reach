<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexMethodRequest extends FormRequest
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
            'search' => ['nullable', $min1 = 'min:1', 'string'],
            'sort_by' => ['nullable', $min1, 'string'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', $min1, 'integer'],
            'page' => ['nullable', 'integer'],
            'paginate' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable'],
            'trashed' => ['nullable', 'string', 'in:with,without,only'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'date_field' => ['nullable', 'string']
        ];
    }

    public function prepareForValidation()
    {
        if ($this->has('date_from')) {
            $this->merge(['date_from' => $this->date_from . ' 00:00:00']);
        }
        if ($this->has('date_to')) {
            $this->merge(['date_to' => $this->date_to . ' 23:59:59']);
        }
    }
}
