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
            'years' => ['required', 'integer', 'min:1', 'max:50'],
            'is_compounded' => ['sometimes', 'boolean'],
            'base_annual_rate' => ['sometimes', 'numeric', 'min:-1', 'max:1'],
            'growth_bonus' => ['sometimes', 'array', 'min:1'],
            'growth_bonus.*' => ['numeric', 'min:-1', 'max:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'base_capital.gt' => 'The base capital must be a positive amount.',
            'years.min' => 'The simulation must span at least one year.',
            'years.max' => 'The simulation cannot exceed 50 years.',
            'is_compounded.boolean' => 'The is_compounded flag must be a boolean.',
            'base_annual_rate.min' => 'The base annual rate cannot be below -100%.',
            'base_annual_rate.max' => 'The base annual rate cannot exceed 100%.',
            'growth_bonus.array' => 'The growth bonus must be provided as a list of annual rates.',
            'growth_bonus.*.min' => 'Each growth bonus cannot be below -100%.',
            'growth_bonus.*.max' => 'Each growth bonus cannot exceed 100%.',
        ];
    }
}
