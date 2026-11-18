<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'name', 'branch_number', 'address', 'phone', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }

    public static function default(): ?self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first();
    }
}
