<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Exceptions;

use RuntimeException;

/**
 * The panel cannot supply context this surface needs, so it shows nothing.
 *
 * Raised when a panel has tenancy switched on. The domain package deliberately
 * holds no tenant or site column — a plan keys on an opaque `order_reference`
 * and nothing else — so there is no column for Filament's tenant scope to bind
 * to. Rendering the table anyway would list every business's plans to whichever
 * tenant happened to be current, which is the one failure this module must not
 * have. Refusing to render is the correct direction: a half-configured
 * deployment sees an error, not somebody else's money.
 */
final class PanelContextUnavailable extends RuntimeException {}
