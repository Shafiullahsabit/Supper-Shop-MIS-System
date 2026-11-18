<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'user_id', 'opening_float', 'cash_sales', 'card_sales', 'other_sales',
        'cash_refunds', 'expected_cash', 'counted_cash', 'difference',
        'orders_count', 'status', 'opened_at', 'closed_at', 'note',
    ];

    protected $casts = [
        'opening_float' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'other_sales' => 'decimal:2',
        'cash_refunds' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
