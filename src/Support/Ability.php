<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Support;

/**
 * The three decisions this package hands back to the host.
 *
 * Everything else the operator surface could do is refused structurally — the
 * ledger is append-only and a tender no gateway ever saw cannot be invented, so
 * there is nothing for a deployment to grant. These three are the only
 * questions a deployment gets to answer, and Laravel's gate answers all three
 * with a denial until it is told otherwise: an ability nobody defined resolves
 * to null, which `Gate::inspect()` turns into a denial. Failing closed is the
 * default rather than something this package has to remember to arrange.
 */
enum Ability: string
{
    /** Read plans, the tender ledger and outstanding balances. */
    case View = 'ecommerce-multi-tender-payments.view';

    /** Append a reversal to the ledger. The domain still decides whether it may happen. */
    case Reverse = 'ecommerce-multi-tender-payments.reverse';

    /**
     * See the reference the module that actually moved the money handed back.
     *
     * Separate from {@see self::View} because a reconciliation clerk needs the
     * ledger and does not need the gateway's identifiers for it.
     */
    case ViewGatewayReference = 'ecommerce-multi-tender-payments.view-gateway-reference';
}
