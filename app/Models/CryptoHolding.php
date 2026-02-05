<?php

use App\Models\User;
use App\Models\TradeCurrency;
use Illuminate\Database\Eloquent\Model;

class CryptoHolding extends Model
{
    protected $fillable = [
        'user_id',
        'trade_currency_id',
        'balance',
    ];

    public function tradeCurrency()
    {
        return $this->belongsTo(TradeCurrency::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
