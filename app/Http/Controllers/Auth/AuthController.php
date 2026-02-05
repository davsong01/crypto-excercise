<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\HttpResponseService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

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

            // Create Naira wallet
            Wallet::create([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'NGN',
            ]);

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
}
