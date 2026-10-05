<?php

namespace App\Models\Traits;

use App\Models\Scopes\BranchScope;

trait BranchScoped
{
    /**
     * Boot the branch scoped trait for a model.
     */
    protected static function bootBranchScoped(): void
    {
        static::addGlobalScope(new BranchScope);

        // Auto-assign branch_id when creating if user is employee and branch_id is not explicitly set
        static::creating(function ($model) {
            $user = auth()->user();
            if ($user && $user->role === \App\Enums\Role::EMPLOYEE && $user->branch_id && empty($model->branch_id)) {
                $model->branch_id = $user->branch_id;
            }
        });
    }
}
