<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function walletBalance(int $userId): float
    {
        $wallet = Wallet::where('user_id', $userId)->first();
        return $wallet?->balance ?? 0;
    }

    public function walletLog(float $amount, ?string $reference, string $type, int $userId, string $duplicate_check): WalletLog
    {
        $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

        if (!$wallet) {
            $wallet = Wallet::create([
                'user_id' => $userId,
                'balance' => 0,
            ]);

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
        }

        $initialBalance = $wallet->balance;

        if ($type === 'credit') {
            $finalBalance = $initialBalance + $amount;
            $wallet->balance = $finalBalance;
        } elseif ($type === 'debit') {
            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.']
                ]);
            }
            $finalBalance = $initialBalance - $amount;
            $wallet->balance = $finalBalance;
        } else {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient wallet balance.']
            ]);
        }

        $wallet->save();

        return WalletLog::create([
            'user_id' => $userId,
            'reference' => $reference,
            'initial_balance' => $initialBalance,
            'final_balance' => $finalBalance,
            'amount' => $amount,
            'type' => $type,
            'duplicate_check' => $duplicate_check,
        ]);
    }

}
