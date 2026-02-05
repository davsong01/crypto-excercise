<?php

namespace App\Services;

use Exception;
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

    public function logTransaction(int $userId, string $type, float $amount, $status='initiated', ?string $currencyId = null): Transaction
    {
        $reference = now()->format('YmdHis') . rand(1000, 9999);
        $duplicateCheck = $userId . '|' . $type . '|' . $amount . '|' . ($currencyId ?? '') . '|' . $reference;

        $feeAmount = 0;
        $totalAmount = $amount;
        $tradeCurrencyId = null;

        if (in_array($type, ['buy', 'sell'])) {
            $currency = TradeCurrency::where('id', $currencyId)->firstOrFail();

            $feeAmount = $currency->fee_type === 'percentage'
                ? ($amount * $currency->fee / 100)
                : $currency->fee;

            $totalAmount = $type === 'buy' ? $amount + $feeAmount : $amount - $feeAmount;
            $tradeCurrencyId = $currency->id;

            if ($type === 'buy') {
                $this->walletService->walletLog($totalAmount, $reference, 'debit', $userId, $duplicateCheck);
            } else {
                $this->walletService->walletLog($amount, $reference, 'credit', $userId, $duplicateCheck);
            }
        } elseif ($type === 'deposit') {
            $this->walletService->walletLog($amount, $reference, 'credit', $userId, $duplicateCheck);
        } elseif ($type === 'withdraw') {
            $this->walletService->walletLog($amount, $reference, 'debit', $userId, $duplicateCheck);
        } else {
            throw new \InvalidArgumentException('Invalid transaction type');
        }

        return Transaction::create([
            'user_id'           => $userId,
            'trade_currency_id' => $tradeCurrencyId,
            'type'              => $type,
            'amount'            => $amount,
            'fee'               => $feeAmount,
            'total_amount'      => $totalAmount,
            'reference'         => $reference,
            'status'            => $status,
            'duplicate_check'   => $duplicateCheck,
        ]);
    }
}
