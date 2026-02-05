<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletLog extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'initial_balance',
        'final_balance',
        'amount',
        'type',
        'duplicate_check',
    ];

    protected $casts = [
        'amount'          => 'float',
        'initial_balance' => 'float',
        'final_balance'   => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // public function scopeByDuplicateCheck($query, string $duplicateCheck)
    // {
    //     return $query->where('duplicate_check', $duplicateCheck);
    // }
}
