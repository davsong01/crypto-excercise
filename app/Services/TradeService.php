<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\CryptoHolding;
use App\Models\TradeCurrency;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
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


    public function buyCrypto(array $payload): array
    {
        // I create a transaction first so we have a record of every transaction instead of rolling back everything and leaving the user hanging when something goes wrong
        $transaction = $this->transactionService->logTransaction(
            userId: $payload['user_id'],
            type: 'buy',
            amount: $payload['nairaAmount'],
            status: 'initiated',
            currency: $payload['currency'],
            conversion_rate: $payload['conversionRate'],
            feeAmount: $payload['feeAmount'],
            cryptoAmount: $payload['cryptoAmount'],
        );

        try {
            DB::beginTransaction();

            // Update holdings
            $holding = CryptoHolding::firstOrCreate(
                ['user_id' => $payload['user_id'], 'trade_currency_id' => $payload['currency']->id],
                ['balance' => 0]
            );
            $holding->balance += $payload['cryptoAmount'];
            $holding->save();

            $transaction->update(['status' => 'completed']);
            $this->transactionService->logTransactionWallet($transaction);

            DB::commit();

            return ['status' => true, 'transaction' => $transaction];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['status' => false, 'transaction' => $transaction, 'message' => $e->getMessage()];
        }
    }

    public function sellCrypto(array $payload): array
    {
        $userId = $payload['user_id'];
        $currency = $payload['currency'];

        // Ensure holdings cover crypto to sell
        $holding = CryptoHolding::firstOrCreate(
            ['user_id' => $userId, 'trade_currency_id' => $currency->id],
            ['balance' => 0]
        );

        if ($holding->balance < $payload['cryptoAmount']) {
            return ['status' => false, 'message' => 'Insufficient crypto balance.'];
        }

        $transaction = $this->transactionService->logTransaction(
            userId: $userId,
            type: 'sell',
            amount: $payload['nairaAmount'],
            status: 'initiated',
            currency: $currency,
            conversion_rate: $payload['conversionRate'],
            feeAmount: $payload['feeAmount'],
            cryptoAmount: $payload['cryptoAmount'],
        );

        try {
            DB::beginTransaction();

            // Deduct crypto from holdings
            $holding->balance -= $payload['cryptoAmount'];
            $holding->save();

            // Complete transaction and log wallet
            $transaction->update(['status' => 'completed']);
            $this->transactionService->logTransactionWallet($transaction);

            DB::commit();

            return ['status' => true, 'transaction' => $transaction];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['status' => false, 'transaction' => $transaction, 'message' => $e->getMessage()];
        }
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
