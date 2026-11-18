<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function record(string $action, string $description, $subject = null): void
    {
        static::create([
            'branch_id' => $subject?->branch_id,
            'user_id' => Auth::id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject->id ?? null,
            'ip_address' => request()->ip(),
        ]);
    }
}
