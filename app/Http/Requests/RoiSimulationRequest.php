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
            'months' => ['required', 'integer', 'min:1', 'max:360'],
            'expected_monthly_rate' => ['required', 'numeric', 'min:-1', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'base_capital.gt' => 'The base capital must be a positive amount.',
            'months.min' => 'The simulation must span at least one month.',
            'months.max' => 'The simulation cannot exceed 360 months.',
            'expected_monthly_rate.min' => 'The monthly rate cannot be below -100%.',
            'expected_monthly_rate.max' => 'The monthly rate cannot exceed 100%.',
        ];
    }
}