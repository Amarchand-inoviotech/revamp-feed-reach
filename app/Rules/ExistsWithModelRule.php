<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class ExistsWithModelRule implements ValidationRule
{
    protected $table;
    protected $model;
    protected $column;

    /**
     * Constructor to accept dynamic model value and column.
     *
     * @param string $table
     * @param string $model
     * @param string $column
     */
    public function __construct(string $table, string $model, string $column = 'id')
    {
        $this->table = $table;
        $this->model = $model;
        $this->column = $column;
    }


    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->where($this->column, $value)
            ->where('model', $this->model)
            ->exists();

        // If it does not exist, fail the validation
        if (! $exists) {
            $fail(trans('validation.exist_with_model', [
                'attribute' => $attribute,
                'model' => $this->model,
            ]));
        }
    }
}
