<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name', 'sku', 'barcode', 'category_id', 'unit_id', 'supplier_id',
        'cost_price', 'sale_price', 'tax_rate', 'stock_quantity', 'alert_quantity',
        'image', 'description', 'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'alert_quantity' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    protected $appends = ['stock_status'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock_quantity', '<=', 'alert_quantity');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity <= 0) {
            return 'out';
        }

        return $this->stock_quantity <= $this->alert_quantity ? 'low' : 'in';
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->sale_price <= 0) {
            return 0;
        }

        return round((($this->sale_price - $this->cost_price) / $this->sale_price) * 100, 2);
    }
}
