<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\HttpResponseService;
use App\Http\Resources\TransactionResource;

class TransactionController extends Controller{
    public function transactions(Request $request)
    {
        $user = auth()->user();

        $type = $request->query('type');           // buy, sell, deposit, withdraw
        $currency_id = $request->query('currency_id');
        $status = $request->query('status');

        $query = $user->transactions()->with('tradeCurrency');

        if ($type) {
            $query->where('type', $type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($currency_id) {
            $query->whereHas('tradeCurrency', function ($q) use ($currency_id) {
                $q->where('id', $currency_id);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $perPage = $request->query('per_page', 20);
        $transactions = $query->paginate($perPage);


        $transactions = $query->orderBy('created_at', 'desc')->paginate($request->query('per_page', 20));

        $transactions = $query->orderBy('created_at', 'desc')
                          ->paginate($request->query('per_page', 20));

        return TransactionResource::collection($transactions);
    }

}
