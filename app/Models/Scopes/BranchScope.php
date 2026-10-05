<?php

namespace App\Models\Scopes;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // If authenticated and is an employee, scope query to employee's branch
        if ($user && $user->role === Role::EMPLOYEE && $user->branch_id) {
            $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
        }
    }
}
