<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Exceptions\PanelContextUnavailable;

/**
 * Everything this surface needs to know about who is looking, resolved from
 * the panel rather than from anything a request carried.
 *
 * Three kinds of context, and each fails closed when it is absent:
 *
 * - **The actor** comes from the panel's own guard, never from a parameter. No
 *   authenticated actor means every ability answers no, rather than answering
 *   with whatever the default guard happened to hold.
 * - **The tenant** does not exist. The domain package publishes no tenant or
 *   site column on purpose, so a panel with tenancy switched on has nothing to
 *   scope by and this surface refuses to render at all — see
 *   {@see PanelContextUnavailable}. Listing every business's plans under one
 *   tenant's heading would be a worse answer than an error.
 * - **The locale** never reaches a money figure. Amounts are rendered from the
 *   domain's own `Money::decimal()` string, which is exact integer minor units
 *   turned into digits by string arithmetic; no locale, no rounding and no
 *   re-derivation is involved. Timestamps do vary, and they take the
 *   application's configured timezone — trusted server-side configuration —
 *   rather than anything a viewer supplied.
 */
final class OperatorContext
{
    /** Who the panel says is looking, or null. */
    public static function actor(): ?Authenticatable
    {
        return Filament::getCurrentOrDefaultPanel()?->auth()->user();
    }

    /**
     * Whether the host has granted this ability to the current actor.
     *
     * Deliberately not routed through Filament's `get_authorization_response()`:
     * that helper answers *allow* when a policy exists but lacks the method it
     * was asked about, which is the wrong default for anything to do with
     * money. Laravel's own gate answers a question nobody defined with a
     * denial, and that is the answer this surface wants.
     */
    public static function grants(Ability $ability): bool
    {
        $actor = self::actor();

        return $actor !== null && Gate::forUser($actor)->allows($ability->value);
    }

    /** The timezone every timestamp on this surface is rendered in. */
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }

    /**
     * Refuse to read plans at all when the panel expects a tenant column.
     *
     * @throws PanelContextUnavailable
     */
    public static function assertPlansAreScopable(): void
    {
        if (Filament::getCurrentOrDefaultPanel()?->hasTenancy() === true) {
            throw new PanelContextUnavailable(
                'This panel has tenancy enabled, but multi-tender payment plans carry no tenant column. '
                .'Scope them at the resolver seam the domain package publishes, or attach this plugin to an untenanted panel.'
            );
        }
    }
}
