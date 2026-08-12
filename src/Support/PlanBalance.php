<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Support;

use Illuminate\Contracts\Container\BindingResolutionException;
use Liberu\Ecommerce\MultiTenderPayments\Exceptions\PayableTotalUnknown;
use Liberu\Ecommerce\MultiTenderPayments\Models\PaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Queries\OutstandingBalance;
use Liberu\Ecommerce\MultiTenderPayments\Support\Money;

/**
 * What a plan still owes, asked of the domain every time it is displayed.
 *
 * There is no balance column to read and this package must never invent one.
 * The figure is a fold over the append-only ledger, so it is computed by
 * {@see OutstandingBalance} on each render — including the reversal the
 * operator just recorded, which changes the answer without changing any stored
 * total.
 *
 * Two absences are told apart and neither is shown as a number:
 *
 * - the host has not bound `ResolvesPayableTotal` at all, which is a
 *   deployment fault the domain deliberately leaves unbound;
 * - the resolver answered null for this order, which is a fact about the order.
 *
 * Both render as {@see self::UNKNOWN}. Displaying zero for either would be this
 * package inventing a total the host declined to give it, and a zero balance
 * reads as "settled".
 *
 * One resolver call per row. That is an N+1 against whatever the host's
 * resolver talks to, and it is the honest cost of never caching a balance: an
 * operator screen paging twenty-five plans makes twenty-five calls. A
 * deployment that finds it expensive caches inside its own resolver, which is
 * the only place that knows whether a total is cacheable.
 */
final class PlanBalance
{
    public const UNKNOWN = 'Unknown';

    public static function forPlan(PaymentPlan $plan): ?Money
    {
        try {
            return app(OutstandingBalance::class)->forPlan($plan);
        } catch (BindingResolutionException|PayableTotalUnknown) {
            return null;
        }
    }

    /**
     * The balance as text.
     *
     * `Money::decimal()` is the domain's own presentation and is produced by
     * string arithmetic over integer minor units. Nothing here divides,
     * rounds, or re-derives a figure — formatting for display is the only
     * thing this method is allowed to do to somebody's money.
     */
    public static function label(PaymentPlan $plan): string
    {
        $balance = self::forPlan($plan);

        return $balance instanceof Money
            ? $balance->decimal().' '.$balance->currency
            : self::UNKNOWN;
    }
}
