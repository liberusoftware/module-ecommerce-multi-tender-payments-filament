<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Tests\Fixtures;

use Liberu\Ecommerce\MultiTenderPayments\Contracts\ResolvesPayableTotal;
use Liberu\Ecommerce\MultiTenderPayments\Support\Money;

/**
 * The half of the host this surface depends on.
 *
 * The domain publishes `ResolvesPayableTotal` and deliberately leaves it
 * unbound, so a deployment that has not bound it is a state this package has to
 * render rather than crash on. Tests bind this, or bind nothing, on purpose.
 */
final class FakeHost implements ResolvesPayableTotal
{
    /** @var array<string, Money|null> */
    public array $totals = [];

    public function payableTotalFor(string $orderReference): ?Money
    {
        return $this->totals[$orderReference] ?? null;
    }

    public function total(string $orderReference, ?Money $money): self
    {
        $this->totals[$orderReference] = $money;

        return $this;
    }
}
