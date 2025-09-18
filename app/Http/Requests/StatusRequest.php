<?php

namespace App\Http\Requests;

use App\Rules\UniqueWithRule;
use App\Rules\ValidateModelRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatusRequest extends FormRequest
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
        $model = $this->route('source');

        return [
            'name' => ['required', 'string', 'max:255', new UniqueWithRule(
                table: 'statuses',
                column: 'name',
                conditions: ['model' => $this->model],
                ignore: $model?->id
            )],
            'model' => ['required', 'string', 'max:255', new ValidateModelRule()],
            'color' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
        ];
    }
}
