<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'invoice_no', 'client_uuid', 'customer_id', 'user_id', 'cash_session_id', 'subtotal',
        'discount', 'tax', 'total', 'paid', 'change_due', 'payment_method',
        'status', 'is_offline', 'offline_created_at', 'note',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'change_due' => 'decimal:2',
        'is_offline' => 'boolean',
        'offline_created_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function getProfitAttribute(): float
    {
        return (float) $this->items->sum(fn ($i) => ($i->price - $i->cost) * $i->quantity - $i->discount);
    }

    public static function generateInvoiceNo(): string
    {
        $prefix = 'INV-'.date('Ymd').'-';
        $last = static::where('invoice_no', 'like', $prefix.'%')->orderByDesc('id')->value('invoice_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
