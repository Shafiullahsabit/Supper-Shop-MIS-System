<?php

namespace App\Support;

use App\Models\Branch;
use Illuminate\Support\Facades\Auth;

class BranchContext
{
    public static function id(): ?int
    {
        $user = Auth::user();

        if ($user?->hasRole('Super Admin')) {
            $activeId = session('active_branch_id') ?: $user->branch_id;
            if ($activeId && Branch::whereKey($activeId)->where('is_active', true)->exists()) {
                return (int) $activeId;
            }

            return Branch::default()?->id;
        }

        if ($user?->branch_id && Branch::whereKey($user->branch_id)->where('is_active', true)->exists()) {
            return (int) $user->branch_id;
        }

        return $user ? null : Branch::default()?->id;
    }
}
