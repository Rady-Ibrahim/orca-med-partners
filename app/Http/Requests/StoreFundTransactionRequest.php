<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\FundTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreFundTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_type' => ['required', Rule::enum(FundTransactionType::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'transaction_date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'monthly_profit_id' => ['nullable', 'integer', 'exists:monthly_profits,id'],
        ];
    }
}
