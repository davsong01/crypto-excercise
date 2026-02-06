<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CryptoHolding;
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
        $cryptoAmount = (float) $request->amount;

        if ($cryptoAmount < $currency->min_trade_amount) {
            return HttpResponseService::error(
                'Transaction failed',
                ["message" => "Minimum transaction amount for {$currency->name} is {$currency->min_trade_amount} {$currency->symbol}"],
                'general',
                422
            );
        }

        $nairaRate = $this->tradeService->getNairaRate($currency);
        if (!$nairaRate) {
            return HttpResponseService::error(
                'Transaction failed',
                ['message' => 'Unable to fetch current exchange rate, please try again later.'],
                'general',
                422
            );
        }

        $nairaAmount = round($cryptoAmount * $nairaRate, 2);
        $feeAmount = $this->transactionService->getFeeAmount($currency, $nairaAmount);

        $buyResponse = $this->tradeService->buyCrypto([
            'user_id' => $userId,
            'currency' => $currency,
            'cryptoAmount' => $cryptoAmount,
            'nairaAmount' => $nairaAmount,
            'feeAmount' => $feeAmount,
            'conversionRate' => $nairaRate,
        ]);

        if ($buyResponse['status']) {
            return HttpResponseService::success(
                'Crypto purchased successfully',
                new TransactionResource($buyResponse['transaction'])
            );
        }

        return HttpResponseService::error(
            'Transaction failed',
            ['message' => $buyResponse['message'] ?? 'Something went wrong'],
            'general',
            400
        );
    }

    public function sell(TradeRequest $request)
    {
        $user = $request->user();
        $userId = $user->id;
        $currency = TradeCurrency::findOrFail($request->currency_id);
        $cryptoAmount = (float) $request->amount; // user enters crypto to sell

        if ($cryptoAmount < $currency->min_trade_amount) {
            return HttpResponseService::error(
                'Transaction failed',
                ["message" => "Minimum transaction amount for {$currency->name} is {$currency->min_trade_amount} {$currency->symbol}"],
                'general',
                422
            );
        }

        $nairaRate = $this->tradeService->getNairaRate($currency);
        if (!$nairaRate) {
            return HttpResponseService::error(
                'Transaction failed',
                ['message' => 'Unable to fetch current exchange rate, please try again later.'],
                'general',
                422
            );
        }

        $nairaAmount = round($cryptoAmount * $nairaRate, 2);
        $feeAmount = $this->transactionService->getFeeAmount($currency, $nairaAmount);

        $sellResponse = $this->tradeService->sellCrypto([
            'user_id' => $userId,
            'currency' => $currency,
            'cryptoAmount' => $cryptoAmount,
            'nairaAmount' => $nairaAmount,
            'feeAmount' => $feeAmount,
            'conversionRate' => $nairaRate,
        ]);

        if ($sellResponse['status']) {
            return HttpResponseService::success(
                'Crypto sold successfully',
                new TransactionResource($sellResponse['transaction'])
            );
        }

        return HttpResponseService::error(
            'Transaction failed',
            ['message' => $sellResponse['message'] ?? 'Something went wrong'],
            'general',
            422
        );
    }
}
