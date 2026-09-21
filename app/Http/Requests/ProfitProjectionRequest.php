<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProfitProjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1000', 'max:99999999999999.99'],
            'period_type' => ['required', 'string', Rule::in(['month', 'quarter', 'semi_annual', 'annual', 'years'])],
            'period_value' => ['required', 'integer', 'min:1', 'max:50'],
            'is_compounded' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The amount must be at least 1000.',
            'amount.max' => 'The amount is too large.',
            'period_type.required' => 'The period type is required.',
            'period_type.in' => 'The period type must be one of: month, quarter, semi_annual, annual, years.',
            'period_value.required' => 'The period value is required.',
            'period_value.integer' => 'The period value must be an integer.',
            'period_value.min' => 'The period value must be at least 1.',
            'period_value.max' => 'The period value cannot exceed 50.',
            'is_compounded.boolean' => 'The is_compounded flag must be a boolean.',
        ];
    }
}
