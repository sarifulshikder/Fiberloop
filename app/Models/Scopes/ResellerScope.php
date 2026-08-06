<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Eloquent scope that restricts reseller-role users to only their own records.
 *
 * Applied to: Customer, Subscription, Invoice, Payment.
 * Safe to apply broadly — non-reseller users are never filtered.
 */
class ResellerScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply when a reseller user is authenticated
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();

        if (! $user->hasRole('reseller')) {
            return;
        }

        // The authenticated reseller user must have a linked reseller_id on their User record,
        // OR we look up the Reseller by user email.
        $resellerId = $user->reseller_id ?? null;

        if ($resellerId === null) {
            // Attempt to find by email match
            $reseller = \App\Models\Reseller::where('email', $user->email)->first();
            $resellerId = $reseller?->id;
        }

        if ($resellerId !== null) {
            $builder->where($model->getTable() . '.reseller_id', $resellerId);
        } else {
            // Reseller user with no linked reseller record — see nothing
            $builder->whereRaw('1 = 0');
        }
    }
}
