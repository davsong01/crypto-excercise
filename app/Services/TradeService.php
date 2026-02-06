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
        $userId        = $buyPayload['user_id'];
        $currency      = $buyPayload['currency'];
        $nairaAmount   = $buyPayload['nairaAmount'];
        $cryptoAmount  = $buyPayload['cryptoAmount'];
        $feeAmount     = $buyPayload['feeAmount'];
        $rate          = $buyPayload['conversionRate'];

        $transaction = $this->transactionService->logTransaction(
            userId: $userId,
            type: 'buy',
            amount: $nairaAmount,
            status: 'initiated',
            currency: $currency,
            conversion_rate: $rate,
            feeAmount: $feeAmount,
        );

        // Since this is a test, we assume the integration went well, else return false to break out
        if($transaction){
            // Update crypto holdings
            $this->updateHoldings(
                userId: $userId,
                currencyId: $currency->id,
                cryptoAmount: $cryptoAmount
            );
        }else{
            return [
                'status' => false,
                'message' => 'Failure message',
            ];
        }

        return [
            'status' => true,
            'transaction'   => $transaction
        ];
    }



    public function updateHoldings(int $userId, int $currencyId, float $cryptoAmount): CryptoHolding
    {
        $holding = CryptoHolding::firstOrCreate(
            ['user_id' => $userId, 'trade_currency_id' => $currencyId],
            ['balance' => 0]
        );

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
