<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use App\Services\HttpResponseService;
use App\Http\Resources\TransactionResource;

class TransactionController extends Controller{
    public function transactions(Request $request, TransactionService $transactionService)
    {
        $transactions = $transactionService->transactionHistory($request->only([
            'type', 'currency_id', 'status', 'from', 'to', 'per_page'
        ]));

        return TransactionResource::collection($transactions);
    }


}
