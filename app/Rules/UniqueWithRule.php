<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;

class UniqueWithRule implements ValidationRule
{
    public function __construct(
        private string $table,
        private ?string $column = null,
        private array $conditions = [],
        private mixed $ignore = null
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->column ??= $attribute;
        $rule = (new Unique($this->table, $this->column))
            ->where(function ($query) {
                foreach ($this->conditions as $column => $conditionValue) {
                    $query->where($column, $conditionValue);
                }
            });

        if ($this->ignore !== null) {
            $rule->ignore($this->ignore);
        }

        $validator = validator(
            [$attribute => $value],
            [$attribute => [$rule]]
        );

        if ($validator->fails()) {
            $fail(trans('validation.unique'));
        }
    }
}
