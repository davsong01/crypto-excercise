<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TradeCurrency;
use App\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Services\HttpResponseService;
use App\Http\Requests\Auth\TradeRequest;
use App\Http\Resources\TransactionResource;

class TransactionController extends Controller{
    protected TransactionService $transactionService;
    protected WalletService $walletService;

    public function __construct(TransactionService $transactionService, WalletService $walletService)
    {
        $this->transactionService = $transactionService;
        $this->walletService = $walletService;
    }

    public function currencies($id = null)
    {
        if ($id) {
            $currency = TradeCurrency::find($id, ['id', 'symbol', 'name', 'fee', 'fee_type', 'min_trade_amount']);
            if (!$currency) {
                return HttpResponseService::error('Currency not found', [], 404);
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
        $userId = $request->user()->id;
        $currencyId = $request->currency_id;
        $amount = (float) $request->amount;

        $currency = TradeCurrency::where('id', $currencyId)->firstOrFail();
        $feeAmount = $this->transactionService->getFeeAmount($currency, $amount);

        $walletBalance = $this->walletService->walletBalance($userId);
        // We need to get rates from api and convert to naira now
        if($walletBalance < $amount + $feeAmount){
            return HttpResponseService::error('Transaction failed', ['message' => 'Insufficient wallet balance, please load naira wallet'], 400);
        }

        if($amount < $currency->min_trading_amount){
            return HttpResponseService::error('Transaction failed', ['message' => "You cannot transaction with less than {$currency->min_trading_amount} on this service"], 400);
        }

        try {
            $transaction = $this->transactionService->logTransaction(
                userId: $userId,
                type: 'buy',
                amount: $amount,
                currency: $currency,
                status: 'initiated'
            );

            // we need to now add the amount to the holding

            return HttpResponseService::success('Crypto purchased successfully', $transaction);

        } catch (\RuntimeException $e) {
            return HttpResponseService::error('Transaction failed', ['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return HttpResponseService::fatalError('Unexpected error occurred', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Sell crypto
     */
    public function sell(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency_id' => 'required|exists:trade_currencies,id',
            'amount'      => 'required|numeric|min:0.00000001',
        ]);

        if ($validator->fails()) {
            return HttpResponseService::error('Invalid input', $validator->errors(), 422);
        }

        $userId = $request->user()->id;
        $currencyId = $request->currency_id;
        $amount = (float) $request->amount;

        // Check user holdings
        $holding = $request->user()->cryptoHoldings()->where('trade_currency_id', $currencyId)->first();
        if (!$holding || $holding->balance < $amount) {
            return HttpResponseService::error('Insufficient crypto balance', [], 400);
        }

        try {
            $transaction = $this->transactionService->logTransaction(
                userId: $userId,
                type: 'sell',
                amount: $amount,
                currencyId: $currencyId,
                status: 'completed'
            );

            return HttpResponseService::success('Crypto sold successfully', $transaction);

        } catch (\RuntimeException $e) {
            return HttpResponseService::error('Transaction failed', ['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return HttpResponseService::fatalError('Unexpected error occurred', ['exception' => $e->getMessage()]);
        }
    }
}
