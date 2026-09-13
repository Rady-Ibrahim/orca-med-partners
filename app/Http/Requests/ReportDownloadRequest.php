<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReportDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:pdf,xlsx'],
            'year' => ['nullable', 'integer', 'between:2000,2200'],
        ];
    }

    public function reportFormat(): string
    {
        return (string) $this->validated()['format'];
    }

    public function reportYear(): ?int
    {
        return isset($this->validated()['year']) ? (int) $this->validated()['year'] : null;
    }
}