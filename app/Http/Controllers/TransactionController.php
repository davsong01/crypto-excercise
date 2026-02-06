<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TradeCurrency;
use App\Services\TradeService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Services\HttpResponseService;
use App\Http\Requests\Auth\TradeRequest;
use App\Http\Resources\TransactionResource;

class TransactionController extends Controller{
    protected TransactionService $transactionService;
    protected WalletService $walletService;
    protected TradeService $tradeService;

    public function __construct(TransactionService $transactionService, WalletService $walletService, TradeService $tradeService)
    {
        $this->transactionService = $transactionService;
        $this->walletService = $walletService;
        $this->tradeService = $tradeService;
    }

    public function currencies($id = null)
    {
        if ($id) {
            $currency = TradeCurrency::find($id, ['id', 'symbol', 'name', 'fee', 'fee_type', 'min_trade_amount']);
            if (!$currency) {
                return HttpResponseService::error('Currency not found', [], 'general', 404);
            }
            return HttpResponseService::success('Currency fetched', $currency);
        }

        $currencies = TradeCurrency::all(['id', 'symbol', 'name', 'fee', 'fee_type', 'min_trade_amount']);
        return HttpResponseService::success('Trade currencies fetched', $currencies);
    }


    public function transactions(Request $request)
    {
        $transactions = $this->transactionService->transactionHistory($request->only([
            'type', 'currency_id', 'status', 'from', 'to', 'per_page'
        ]));

        return TransactionResource::collection($transactions);
    }


    public function buy(TradeRequest $request)
    {
        $user = $request->user();
        $userId = $user->id;

        $currency = TradeCurrency::findOrFail($request->currency_id);

        $nairaAmount = (float) $request->amount;

        // Get rate (NGN per 1 crypto)
        $nairaRate = $this->tradeService->getNairaRate($currency);

        if (!$nairaRate) {
            return HttpResponseService::error(
                'Transaction failed',
                ['message' => 'Unable to fetch current exchange rate, please try again later.'],
                'general',
                422
            );
        }

        // Convert to crypto
        $cryptoAmount = $nairaAmount / $nairaRate;

        // Validate min trade (crypto domain)
        if ($cryptoAmount < $currency->min_trade_amount) {
            $minNaira = $currency->min_trade_amount * $nairaRate;

            return HttpResponseService::error(
                'Transaction failed',
                [
                    'message' => "Minimum transaction amount for {$currency->name} is ₦" . number_format($minNaira, 2)
                ],
                'general',
                422
            );
        }

        // Fee in naira
        $feeAmount = $this->transactionService->getFeeAmount($currency, $nairaAmount);

        // Wallet check
        $walletBalance = $this->walletService->walletBalance($userId);

        if ($walletBalance < ($nairaAmount + $feeAmount)) {
            return HttpResponseService::error(
                'Transaction failed',
                ['message' => 'Insufficient wallet balance, please fund your Naira wallet.'],
                'general',
                400
            );
        }

        try {
            DB::beginTransaction();

            $buyResponse = $this->tradeService->buyCrypto([
                'user_id'        => $userId,
                'currency'       => $currency,
                'conversionRate' => $nairaRate,
                'cryptoAmount'   => $cryptoAmount,
                'nairaAmount'    => $nairaAmount,
                'feeAmount'      => $feeAmount,
            ]);
            
            if($buyResponse['status'] && $buyResponse['transaction']){
                $buyResponse['transaction']->update([
                    'status'=>'completed',
                ]);

                DB::commit();

                return HttpResponseService::success(
                    'Crypto purchased successfully',
                    $buyResponse['transaction']
                );
            }else{
                // Depending on the kind of error if this was production, we may update transaction status to pending and commit instead of rolling back
                DB::rollBack();

                return HttpResponseService::error(
                    'Transaction failed',
                    ['message' => $buyResponse['message'] ?? 'Something went wrong'],
                    'general',
                    400
                );
            }

        } catch (\RuntimeException $e) {
            DB::rollBack();

            return HttpResponseService::error(
                'Transaction failed',
                ['message' => $e->getMessage()],
                'general',
                400
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return HttpResponseService::fatalError(
                'Unexpected error occurred',
                ['exception' => $e->getMessage()]
            );
        }

    }



    /**
     * Sell crypto
     */
    // public function sell(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'currency_id' => 'required|exists:trade_currencies,id',
    //         'amount'      => 'required|numeric|min:0.00000001',
    //     ]);

    //     if ($validator->fails()) {
    //         return HttpResponseService::error('Invalid input', $validator->errors(), 422);
    //     }

    //     $userId = $request->user()->id;
    //     $currencyId = $request->currency_id;
    //     $amount = (float) $request->amount;

    //     // Check user holdings
    //     $holding = $request->user()->cryptoHoldings()->where('trade_currency_id', $currencyId)->first();
    //     if (!$holding || $holding->balance < $amount) {
    //         return HttpResponseService::error('Insufficient crypto balance', [], 400);
    //     }

    //     try {
    //         $transaction = $this->transactionService->logTransaction(
    //             userId: $userId,
    //             type: 'sell',
    //             amount: $amount,
    //             currencyId: $currencyId,
    //             status: 'completed'
    //         );

    //         return HttpResponseService::success('Crypto sold successfully', $transaction);

    //     } catch (\RuntimeException $e) {
    //         return HttpResponseService::error('Transaction failed', ['message' => $e->getMessage()], 400);
    //     } catch (\Exception $e) {
    //         return HttpResponseService::fatalError('Unexpected error occurred', ['exception' => $e->getMessage()]);
    //     }
    // }
}
