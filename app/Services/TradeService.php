<?php

namespace App\Services;

use CryptoHolding;
use Illuminate\Support\Str;
use App\Models\TradeCurrency;
use App\Services\WalletService;
use App\Services\TransactionService;

class TradeService
{
    protected WalletService $walletService;
    protected TransactionService $transactionService;

    public function __construct(WalletService $walletService, TransactionService $transactionService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }

    /**
     * Buy crypto using Naira
     */
    public function buyCrypto(int $userId, string $symbol, float $amountNaira): CryptoHolding
    {
        $currency = TradeCurrency::where('symbol', $symbol)->firstOrFail();

        if ($amountNaira < $currency->min_trade_amount) {
            throw new \InvalidArgumentException("Minimum buy amount is {$currency->min_trade_amount} NGN");
        }

        // Calculate fee
        $fee = $currency->fee_type === 'percentage'
            ? ($amountNaira * $currency->fee / 100)
            : $currency->fee;

        $totalDebit = $amountNaira + $fee;

        // Deduct Naira from wallet and log transaction
        $this->transactionService->logTransaction(
            userId: $userId,
            type: 'buy',
            amount: $amountNaira,
            status: 'completed',
            currencyId: $currency->id
        );

        // Convert NGN to crypto
        $cryptoAmount = $this->convertNairaToCrypto($symbol, $amountNaira);

        // Update or create crypto holding
        $holding = CryptoHolding::firstOrNew([
            'user_id' => $userId,
            'trade_currency_id' => $currency->id,
        ]);

        $holding->balance += $cryptoAmount;
        $holding->save();

        return $holding;
    }

    /**
     * Sell crypto to receive Naira
     */
    public function sellCrypto(int $userId, string $symbol, float $cryptoAmount): CryptoHolding
    {
        $currency = TradeCurrency::where('symbol', $symbol)->firstOrFail();

        $holding = CryptoHolding::where('user_id', $userId)
            ->where('trade_currency_id', $currency->id)
            ->firstOrFail();

        if ($cryptoAmount > $holding->balance) {
            throw new \RuntimeException('Insufficient crypto balance');
        }

        // Convert crypto to NGN
        $nairaAmount = $this->convertCryptoToNaira($symbol, $cryptoAmount);

        // Calculate fee
        $fee = $currency->fee_type === 'percentage'
            ? ($nairaAmount * $currency->fee / 100)
            : $currency->fee;

        $netNaira = $nairaAmount - $fee;

        // Log sell transaction
        $this->transactionService->logTransaction(
            userId: $userId,
            type: 'sell',
            amount: $cryptoAmount,
            status: 'completed',
            currencyId: $currency->id
        );

        // Deduct crypto from holding
        $holding->balance -= $cryptoAmount;
        $holding->save();

        return $holding;
    }

    /**
     * Convert NGN to crypto using CoinGecko
     */
    protected function convertNairaToCrypto(string $symbol, float $nairaAmount): float
    {
        $rate = $this->getCryptoRate($symbol); // NGN per crypto unit
        return round($nairaAmount / $rate, 8);
    }

    /**
     * Convert crypto to NGN using CoinGecko
     */
    protected function convertCryptoToNaira(string $symbol, float $cryptoAmount): float
    {
        $rate = $this->getCryptoRate($symbol);
        return round($cryptoAmount * $rate, 2);
    }

    /**
     * Get crypto rate from CoinGecko (placeholder)
     */
    protected function getCryptoRate(string $symbol): float
    {
        // TODO: integrate CoinGecko API
        return match ($symbol) {
            'BTC' => 30_000_000,
            'ETH' => 2_000_000,
            'USDT' => 1000,
            default => throw new \InvalidArgumentException('Unsupported currency')
        };
    }
}
