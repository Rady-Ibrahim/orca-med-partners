<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:191'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'category' => ['nullable', 'string', 'max:50'],
        ];
    }
}