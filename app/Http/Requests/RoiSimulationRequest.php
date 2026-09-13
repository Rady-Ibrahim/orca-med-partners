<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RoiSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_capital' => ['required', 'numeric', 'gt:0', 'max:99999999999999.99'],
            'years' => ['required', 'integer', 'min:1', 'max:100'],
            'expected_annual_rate' => ['required', 'numeric', 'min:-0.99', 'max:1.00'],
        ];
    }

    public function messages(): array
    {
        return [
            'base_capital.gt' => 'The base capital must be a positive amount.',
            'expected_annual_rate.min' => 'The annual rate cannot be below -99%.',
            'expected_annual_rate.max' => 'The annual rate cannot exceed 100%.',
        ];
    }
}