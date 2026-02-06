<?php

namespace App\Services;

use Exception;
use App\Models\Currency;
use App\Models\WalletLog;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\DTOs\TransactionDto;
use App\Models\TradeCurrency;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function transactionHistory(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        $query = $user->transactions()->with('tradeCurrency');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (!empty($filters['currency_id'])) {
            $query->where('trade_currency_id', $filters['currency_id']);
        }

        $perPage = $filters['per_page'] ?? 20;

        return $query->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }

    public function getFeeAmount(TradeCurrency $currency, Float $amount): Float
    {
        return $currency->fee_type === 'percentage' ? ($amount * $currency->fee / 100) : $currency->fee;
    }

    public function logTransaction(int $userId, string $type, float $amount, string $status = 'initiated', ?TradeCurrency $currency = null, ?float $conversion_rate = null, float $feeAmount = 0, float $cryptoAmount = 0
    ): Transaction
    {
        $reference = now()->format('YmdHis') . rand(1000, 9999);
        $tradeCurrencyId = $currency->id ?? null;
        $totalAmount = ($type === 'buy') ? $amount + $feeAmount : $amount - $feeAmount;

        $duplicateCheck = $userId . '|' . $type . '|' . $amount . '|' . ($tradeCurrencyId ?? '') . '|' . $reference;

        $transaction = Transaction::create([
            'user_id'           => $userId,
            'trade_currency_id' => $tradeCurrencyId,
            'type'              => $type,
            'amount'            => $amount,
            'fee'               => $feeAmount,
            'fee_rate'          => $currency->fee ?? null,
            'fee_rate_type'     => $currency->fee_type ?? null,
            'total_amount'      => $totalAmount,
            'reference'         => $reference,
            'conversion_rate'   => $conversion_rate,
            'status'            => $status,
            'crypto_amount'     => $cryptoAmount,
            'duplicate_check'   => $duplicateCheck,
        ]);

        if($transaction->status == 'completed'){
            $this->logTransactionWallet($transaction);
        };

        return $transaction;
    }

    public function logTransactionWallet(Transaction $transaction): WalletLog
    {
        $type = $transaction->type;
        $userId = $transaction->user_id;
        $reference = $transaction->reference;
        $totalAmount = $transaction->total_amount;
        $duplicateCheck = $transaction->duplicate_check;

        return match($type) {
            'buy'  => $this->walletService->walletLog($totalAmount, $reference, 'debit', $userId, $duplicateCheck),
            'sell' => $this->walletService->walletLog($totalAmount, $reference, 'credit', $userId, $duplicateCheck),
            'deposit' => $this->walletService->walletLog($totalAmount, $reference, 'credit', $userId, $duplicateCheck),
            default => throw new \InvalidArgumentException('Invalid transaction type')
        };
    }
}
