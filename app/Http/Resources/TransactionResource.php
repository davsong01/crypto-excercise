<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'status'        => $this->status,
            'amount'          => $this->amount,
            'fee'           => $this->fee,
            'fee_rate'          => (float) $this->fee_rate,
            'fee_rate_type'         => $this->fee_rate_type,
            'conversion_rate'   => (float) $this->conversion_rate,
            'total_amount'=> $this->total_amount,
            'crypto_amount' => number_format($this->crypto_amount, 8, '.', ''),
            'reference'   => $this->reference,
            'currency'    => $this->tradeCurrency ? [
                'id'     => $this->tradeCurrency->id,
                'symbol' => $this->tradeCurrency->symbol,
                'name'   => $this->tradeCurrency->name,
            ] : null,
            'wallet_log' => $this->walletLog ? [
                                'id'     => $this->walletLog->id,
                                'user_id'     => $this->walletLog->user_id,
                                'type'     => $this->walletLog->type,
                                'amount'     => $this->walletLog->amount,
                                'initial_balance'     => $this->walletLog->initial_balance,
                                'final_balance'     => $this->walletLog->final_balance,
                                'reference'     => $this->walletLog->reference,
                            ] : null,
            'created_at'  => $this->created_at->toDateTimeString(),
            'updated_at'  => $this->updated_at->toDateTimeString(),

        ];
    }
}
