<?php

namespace App\Models\Concerns;

use App\Models\Scopes\BranchScope;
use App\Support\BranchContext;
use LogicException;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model): void {
            if (! $model->branch_id) {
                $branchId = BranchContext::id();
                if (! $branchId) {
                    throw new LogicException('An active branch must be selected before creating branch data.');
                }

                $model->branch_id = $branchId;
            }
        });
    }
}
