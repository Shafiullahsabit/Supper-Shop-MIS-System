<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchInventory extends Model
{
    protected $fillable = [
        'branch_id', 'product_id', 'stock_quantity', 'alert_quantity',
    ];

    protected $casts = [
        'stock_quantity' => 'decimal:3',
        'alert_quantity' => 'decimal:3',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
