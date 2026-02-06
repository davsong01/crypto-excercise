<?php

namespace App\Models;

use App\Models\WalletLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'trade_currency_id',
        'type',
        'amount',
        'fee',
        'total_amount',
        'reference',
        'duplicate_check',
        'conversion_rate',
        'status',
    ];

    protected $casts = [
        'amount'       => 'float',
        'fee'          => 'float',
        'total_amount' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tradeCurrency(): BelongsTo
    {
        return $this->belongsTo(TradeCurrency::class);
    }

    public function walletLog()
    {
        return $this->hasOne(WalletLog::class, 'reference', 'reference');
    }

}
