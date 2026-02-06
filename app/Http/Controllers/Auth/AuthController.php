<?php

namespace App\Http\Controllers\Auth;

use Exception;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\HttpResponseService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // We proceed to fund naira wallet with 2m, for testing purposes
            $transactionService = app(TransactionService::class);
            $transaction = $transactionService->logTransaction(
                userId: $user->id,
                type: 'deposit',
                amount: 20000000,
                status: 'completed'
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return HttpResponseService::success('Registration successful', [
                'user'  => $user,
                'token' => $token,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            return HttpResponseService::fatalError(
                'Registration failed',
                ['exception' => $e->getMessage()]
            );
        }
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return HttpResponseService::error(
                'Invalid credentials',
                [],
                'general',
                401
            );
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return HttpResponseService::success('Login successful', [
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return HttpResponseService::success('Logged out successfully');
    }

    public function profile(Request $request)
    {
        $user = auth()->user()->load('wallet:user_id,balance','cryptoHoldings.tradeCurrency');

        return HttpResponseService::success('User profile fetched successfully', [
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'wallet'     => [
                    'balance' => $user->wallet?->balance ?? 0,
                ],
                'crypto_holdings' => $user->cryptoHoldings->map(function ($holding) {
                    return [
                        'id' => $holding->id,
                        'currency_id' => $holding->tradeCurrency->id ?? null,
                        'currency' => $holding->tradeCurrency->symbol ?? null,
                        'balance'   => $holding->balance,
                    ];
                }),
            ],
        ]);
    }
}
