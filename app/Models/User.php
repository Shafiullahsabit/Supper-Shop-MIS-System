<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'last_login_ip',
        'last_login_at',
        'branch_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'branch_id' => 'integer',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->branch_id) {
                $user->branch_id = Branch::default()?->id;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? '/storage/'.ltrim($this->avatar, '/') : null;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function openCashSession()
    {
        return $this->cashSessions()->where('branch_id', BranchContext::id())
            ->where('status', 'open')->latest('opened_at')->first();
    }

    public function getRoleNameAttribute(): string
    {
        return $this->roles->pluck('name')->first() ?? 'User';
    }
}
