# Crypto Trading Platform
A Laravel-based cryptocurrency trading platform with integrated wallet management, transaction tracking, and real-time price feeds via CoinGecko API.

## 1. Setup & Run

1. **Clone the repository**:

```
git clone https://github.com/davsong01/crypto-excercise
cd <repo-folder>
```

2. **Install dependencies**:

```
composer install
```

3. **Configure environment variables**:

```
cp .env.example .env
```

* Set database credentials
* Set CoinGecko API base URL if needed

4. **Run migrations and seeders**:

```
php artisan migrate --seed
```

5. **Start the application**:

```
php artisan serve
```

6. **Run tests (optional)**:

```
php artisan test
```

---

## 2. Design Decisions & Architecture Choices

* **Service-Based Architecture**: Business logic is separated into services (`TradeService`, `TransactionService`, `WalletService`) rather than controllers, making the code reusable, testable, and easier to maintain.

* **Transaction Lifecycle**:

  1. Create a transaction record with status `initiated`.
  2. Update user's crypto holdings (for buys) or reduce holdings (for sells).
  3. Log wallet debit/credit only after transaction succeeds.
  4. Mark transaction as `completed`.
     Ensures traceability even if failures occur mid-process.

* **Separation of Wallet and Crypto Holdings**:

  * Wallet: Tracks Naira balances, handles all debit/credit operations.
  * Crypto Holdings: Tracks BTC, ETH, USDT per user.

* **API Consistency**: `TransactionResource` formats transaction data consistently for all responses.

* **Error Handling & Reliability**: Transactions are created first, then wallet logs are applied only if the transaction succeeds, ensuring audit trail.

---

## 3. Fee Handling (Percentage & Calculation Approach)

* Fees are applied on both buy and sell transactions.
* Buy: Fee is **added** to the Naira amount the user pays.
* Sell: Fee is **deducted** from the Naira proceeds credited to the user's wallet.
* Stored in transaction record: `fee`, `fee_rate`, `fee_rate_type` for historical consistency.

**Example Calculations**:

```
Buy Total (Naira) = CryptoPrice * CryptoAmount + Fee
Sell Proceeds (Naira) = CryptoPrice * CryptoAmount - Fee
```

---

## 4. CoinGecko API Integration

* Rates are fetched from CoinGecko API using `/simple/price` endpoint.
* Used to calculate current Naira value per 1 crypto.
* Abstracted in `TradeService::getNairaRate()` for consistency and test mocking.

---

## 5. Trade-offs / Constraints

* No external payment provider: Wallet is internal; deposits/withdrawals are mocked.
* Simplified error handling: Transaction created before wallet logs.
* Fee on sell: Deducted from proceeds instead of debiting another wallet.
* Limited cryptocurrencies: BTC, ETH, USDT only.

---

## 6. Running Tests

* Tests cover buy/sell, wallet, transaction logging.
* CoinGecko API responses are mocked.

```
php artisan test
```

---

## 7. Approximate Time Spent

* Total estimated time: ~12-15 hours

  * Setup & database modeling: 2-3 hours
  * Services and controllers: 6-7 hours
  * Transaction, fee, and wallet logic: 3-4 hours
  * Testing and API formatting: 1 hour
