<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;
use App\Models\TradeCurrency;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthAndTransactionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_register_successfully()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Dave',
            'email' => 'dave@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => ['user', 'token']
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'dave@test.com'
        ]);
    }

    /** @test */
    public function user_can_login_successfully()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'message',
                     'data' => ['user', 'token']
                 ]);
    }

    /** @test */
    public function user_can_buy_crypto_successfully()
    {
        $user = User::factory()->create();

        $currency = TradeCurrency::firstOrCreate(
            ['symbol' => 'BTC'],
            [
                'name' => 'Bitcoin',
                'fee' => 50,
                'fee_type' => 'fixed',
                'min_trade_amount' => 0.00001,
            ]
        );

        Wallet::create([
            'user_id' => $user->id,
            'balance' => 100000000,
        ]);

        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['ngn' => 2000000]
            ], 200)
        ]);

        $payload = [
            'currency_id' => $currency->id,
            'amount' => 0.001
        ];

        $response = $this->actingAs($user)->postJson('/api/trade/buy', $payload);

        $response->assertStatus(200)
                 ->assertJson(['status' => true]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'buy',
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('crypto_holdings', [
            'user_id' => $user->id,
            'trade_currency_id' => $currency->id
        ]);
    }

    /** @test */
    public function user_wallet_is_reduced_after_buy_transaction()
    {
        $user = User::factory()->create();

        $currency = TradeCurrency::firstOrCreate(
            ['symbol' => 'BTC'],
            [
                'name' => 'Bitcoin',
                'fee' => 50,
                'fee_type' => 'fixed',
                'min_trade_amount' => 0.00001,
            ]
        );

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 5000000
        ]);

        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['ngn' => 1000000]
            ], 200)
        ]);

        $payload = [
            'currency_id' => $currency->id,
            'amount' => 0.0001
        ];

        $initial = $wallet->balance;

        $this->actingAs($user)->postJson('/api/trade/buy', $payload);

        $wallet->refresh();

        $this->assertLessThan($initial, $wallet->balance);
    }

    /** @test */
    public function transaction_fails_if_wallet_balance_is_insufficient()
    {
        $user = User::factory()->create();

        $currency = TradeCurrency::firstOrCreate(
            ['symbol' => 'BTC'],
            [
                'name' => 'Bitcoin',
                'fee' => 50,
                'fee_type' => 'fixed',
                'min_trade_amount' => 0.00001,
            ]
        );

        Wallet::create([
            'user_id' => $user->id,
            'balance' => 1000
        ]);

        Http::fake([
            'https://api.coingecko.com/api/v3/simple/price*' => Http::response([
                'bitcoin' => ['ngn' => 1000000]
            ], 200)
        ]);

        $payload = [
            'currency_id' => $currency->id,
            'amount' => 5 // large crypto amount to exceed wallet
        ];

        $response = $this->actingAs($user)->postJson('/api/trade/buy', $payload);

        $response->assertStatus(400)
                 ->assertJson(['status' => false]);
    }
}
