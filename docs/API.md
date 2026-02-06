# Crypto API Reference

This document describes the main API endpoints, request and response examples, and authentication usage for the Crypto Trading Platform.

Base URL (local dev):

```
http://127.0.0.1:8000/api
```

## Required Headers

All API requests should include the following headers to request JSON responses and authenticate when needed:

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer <token>   # for protected endpoints
```

If `Accept: application/json` is not provided, Laravel may return HTML error pages for some error responses; including this header ensures the API always returns JSON.

## Authentication

All successful responses follow the JSON envelope:

```json
{
  "status": true,
  "message": "...",
  "data": { ... }
}
```

Errors return:

```json
{
  "status": false,
  "error_type": "validation|general|fatal",
  "message": "...",
  "errors": { ... }
}
```

### Register

POST /auth/register

Request (JSON):

```json
{
  "name": "Alice",
  "email": "alice@example.com",
  "password": "password"
}
```

Curl:

```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Alice","email":"alice@example.com","password":"password"}'
```

Success Response (201):

```json
{
  "status": true,
  "message": "Registration successful",
  "data": {
    "user": { "id": 1, "name": "Alice", "email": "alice@example.com", ... },
    "token": "<plain-text-token>"
  }
}
```

Save the `token` for authenticated requests.

### Login

POST /auth/login

Request (JSON):

```json
{
  "email": "alice@example.com",
  "password": "password"
}
```

Success Response (200): same envelope with `data.user` and `data.token`.

### Logout

POST /auth/logout (protected — Bearer token required)

Headers:

```
Authorization: Bearer <token>
```

Success Response (200):

```json
{ "status": true, "message": "Logged out successfully", "data": [] }
```

## Profile

GET /profile (protected)

Returns the authenticated user's profile, wallet balance and crypto holdings.

Success Response (200):

```json
{
  "status": true,
  "message": "User profile fetched successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "Alice",
      "email": "alice@example.com",
      "wallet": { "balance": 100000.00 },
      "crypto_holdings": [
        { "id": 1, "currency_id": 1, "currency": "BTC", "balance": "0.00500000" }
      ]
    }
  }
}
```

## Trade Currencies

GET /trade/currencies

Returns list of supported trade currencies (BTC, ETH, USDT seeded by migration).

Success Response:

```json
{ "status": true, "message": "Trade currencies fetched", "data": [ { "id":1, "symbol":"BTC", "name":"Bitcoin", "fee":1.5, "fee_type":"percentage", "min_trade_amount":0.0001 } ] }
```

GET /trade/currencies/{id}

Returns single currency or 404 if not found.

## Transactions / Trading

All trade endpoints are under `/trade` and require authentication.

### Buy crypto

POST /trade/buy (protected)

Request (JSON):

```json
{
  "currency_id": 1,
  "amount": 0.001
}
```

Notes:
- `amount` is the crypto amount (e.g., BTC) the user wants to buy.
- Controller enforces `min_trade_amount` for the selected currency.
- The system fetches a naira conversion rate from CoinGecko and computes the Naira amount and fee.

Success Response (200):

```json
{
  "status": true,
  "message": "Crypto purchased successfully",
  "data": {
    "id": 123,
    "type": "buy",
    "status": "completed",
    "amount": "200000.00",
    "fee": "3000.00",
    "fee_rate": 1.5,
    "fee_rate_type": "percentage",
    "conversion_rate": 200000000.0,
    "total_amount": "203000.00",
    "crypto_amount": "0.00100000",
    "reference": "202602061234561234",
    "currency": { "id":1, "symbol":"BTC", "name":"Bitcoin" },
    "wallet_log": { "id": 11, "user_id":1, "type":"debit", "amount":203000.00, "initial_balance":1000000.00, "final_balance":797000.00, "reference":"..." },
    "created_at": "2026-02-06 10:00:00"
  }
}
```

Failure cases:
- 422 if conversion rate unavailable or amount below minimum.
- 422 if trade service returns an error.

### Sell crypto

POST /trade/sell (protected)

Request (JSON):

```json
{
  "currency_id": 1,
  "amount": 0.001
}
```

Notes:
- `amount` is the crypto amount the user wants to sell.
- The system validates user crypto holdings before proceeding.

Success Response (200): similar to the buy response with `type: "sell"` and the wallet log type `credit`.

Failure cases:
- 422 if insufficient crypto holdings (trade service returns status false).
- 422 if min amount or conversion rate issues.

## Transaction History

GET /transactions (protected)

Query parameters supported: `type`, `currency_id`, `status`, `from`, `to`, `per_page`.

Example:

```
GET /transactions?type=buy&per_page=20
```

Response: paginated collection of `TransactionResource` objects (enclosed in the standard `data` envelope).

## Error handling & validation

Validation and errors use the envelope format with `status: false` and `error_type` (`validation`, `general`, `fatal`). Validation errors return a 422 status code.

## Testing notes

- Tests mock CoinGecko with `Http::fake()` so no external network is required.
- Use `php artisan test --filter=AuthAndTransactionTest` to run the critical feature tests.

---
