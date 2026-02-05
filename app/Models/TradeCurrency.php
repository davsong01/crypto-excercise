<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TradeCurrency extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'fee',
        'fee_type',
        'min_trade_amount'
    ];
}
