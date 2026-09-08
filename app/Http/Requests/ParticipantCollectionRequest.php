<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ParticipantCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('read')) {
            $this->merge(['read' => filter_var($this->input('read'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]);
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'between:2000,2200'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'status' => ['nullable', 'string', 'max:30'],
            'read' => ['nullable', 'boolean'],
        ];
    }

    public function filters(): array
    {
        return array_filter($this->validated(), static fn($value) => $value !== null && $value !== '');
    }
}
