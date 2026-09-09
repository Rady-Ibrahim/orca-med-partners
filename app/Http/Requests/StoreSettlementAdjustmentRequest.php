<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSettlementAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settlement_payment_id' => ['nullable', 'integer', 'exists:settlement_payments,id'],
            'type' => ['required', 'in:adjustment,reversal'],
            'direction' => ['required', 'in:increase,decrease'],
            'amount' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
