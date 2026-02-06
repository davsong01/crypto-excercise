<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency_id' => ['required', 'exists:trade_currencies,id'],
            'amount'      => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.required' => 'Trade currency is required.',
            'currency_id.exists'   => 'Selected currency does not exist.',
            'amount.required'      => 'Trade amount is required.',
            'amount.numeric'       => 'Trade amount must be a valid number.',
            'amount.min'           => 'Trade amount must be greater than 0.',
        ];
    }
}
