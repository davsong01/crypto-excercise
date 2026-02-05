<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'amount'      => $this->amount,
            'fee'         => $this->fee,
            'total_amount'=> $this->total_amount,
            'status'      => $this->status,
            'reference'   => $this->reference,
            'currency'    => $this->tradeCurrency ? [
                'id'     => $this->tradeCurrency->id,
                'symbol' => $this->tradeCurrency->symbol,
                'name'   => $this->tradeCurrency->name,
            ] : null,
            'wallet_log' => in_array($this->type, ['deposit', 'withdraw'])
                            ?  [
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
