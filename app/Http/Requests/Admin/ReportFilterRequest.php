<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['report' => $this->route('report')]);
    }

    public function rules(): array
    {
        return [
            'report' => ['required', 'string'],
            'year' => ['nullable', 'integer', 'between:2000,2200'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'status' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'transaction_type' => ['nullable', 'in:deposit,withdrawal,adjustment'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter($this->validated(), static fn($value, $key) => $key !== 'report' && $value !== null && $value !== '', ARRAY_FILTER_USE_BOTH);
    }
}
