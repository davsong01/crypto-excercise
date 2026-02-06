<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\CryptoHolding;
use App\Models\TradeCurrency;
use App\Services\WalletService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Http;
use App\Services\HttpResponseService;

class TradeService
{
    protected WalletService $walletService;
    protected TransactionService $transactionService;

    public function __construct(WalletService $walletService, TransactionService $transactionService)
    {
        $this->walletService = $walletService;
        $this->transactionService = $transactionService;
    }


    public function buyCrypto(array $buyPayload): array
    {
        $userId       = $buyPayload['user_id'];
        $currency     = $buyPayload['currency'];
        $nairaAmount  = $buyPayload['nairaAmount'];
        $cryptoAmount = $buyPayload['cryptoAmount'];
        $feeAmount    = $buyPayload['feeAmount'];
        $rate         = $buyPayload['conversionRate'];

        // Create transaction with 'initiated' status first
        $transaction = $this->transactionService->logTransaction(
            userId: $userId,
            type: 'buy',
            amount: $nairaAmount,
            status: 'initiated',
            currency: $currency,
            conversion_rate: $rate,
            feeAmount: $feeAmount,
            cryptoAmount: $cryptoAmount,
        );

        $holding = CryptoHolding::firstOrCreate(
            ['user_id' => $userId, 'trade_currency_id' => $currency->id],
            ['balance' => 0]
        );

        $holding->balance += $cryptoAmount;
        $holding->save();

        if($holding){
            // Mark transaction as completed
            $transaction->update(['status' => 'completed']);
            
            $this->transactionService->logTransactionWallet($transaction);
        }else{
            return [
                'status' => false,
                'message' => 'We could not complete the transaction',
            ];
        }

        return [
            'status'      => true,
            'transaction' => $transaction,
        ];
    }


    public function sellCrypto(array $sellPayload): array
    {
        $userId       = $sellPayload['user_id'];
        $currency     = $sellPayload['currency'];
        $cryptoAmount = $sellPayload['cryptoAmount'];
        $nairaAmount  = $sellPayload['nairaAmount'];
        $feeAmount    = $sellPayload['feeAmount'];
        $rate         = $sellPayload['conversionRate'];

        // Log transaction and credit wallet (Naira)
        $transaction = $this->transactionService->logTransaction(
            userId: $userId,
            type: 'sell',
            amount: $nairaAmount,
            status: 'initiated',
            currency: $currency,
            conversion_rate: $rate,
            feeAmount: $feeAmount
        );

        if ($transaction) {
            // Subtract crypto from holding
            $holding = CryptoHolding::where('user_id', $userId)
                ->where('trade_currency_id', $currency->id)
                ->firstOrFail();

            $holding->balance -= $cryptoAmount;
            $holding->save();

            // Credit Naira wallet after fee
            $this->walletService->walletLog(
                $nairaAmount - $feeAmount,
                $transaction->reference,
                'credit',
                $userId,
                $transaction->duplicate_check
            );
        } else {
            return [
                'status' => false,
                'message' => 'Failed to log sell transaction',
            ];
        }

        return [
            'status' => true,
            'transaction' => $transaction
        ];
    }

    public function getNairaRate($currency): ?float
    {
        try {
            $symbol = $currency->symbol;
            $symbolMap = [
                'BTC'  => 'bitcoin',
                'ETH'  => 'ethereum',
                'USDT' => 'tether',
            ];

            if (!isset($symbolMap[$symbol])) {
                return null;
            }

            $id = $symbolMap[$symbol];

            $response = Http::timeout(5)->get("https://api.coingecko.com/api/v3/simple/price", [
                'ids' => $id,
                'vs_currencies' => 'ngn',
            ]);

            if (!$response->successful()) {
                logger()->warning('CoinGecko API failed', [
                    'status' => $response->status(),
                    'body'   => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (!is_array($data) || !isset($data[$id]['ngn'])) {
                logger()->warning('Invalid CoinGecko response format', [
                    'response' => $data
                ]);
                return null;
            }

            return $data[$id]['ngn'];

        } catch (\Exception $e) {
            logger()->error("Failed to fetch rate for {$symbol}: " . $e->getMessage());
            return null;
        }
    }

}
