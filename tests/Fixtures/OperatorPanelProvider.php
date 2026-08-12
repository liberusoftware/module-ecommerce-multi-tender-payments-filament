<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\MultiTenderPayments\Filament\MultiTenderPaymentsPlugin;

/**
 * The panel a host would attach this package to.
 *
 * It attaches the plugin and nothing else, so every resource, page and action
 * the tests find came from this package's own discovery rather than from a
 * fixture that registered it by hand.
 */
final class OperatorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('operator')
            ->path('operator')
            ->plugin(MultiTenderPaymentsPlugin::make());
    }
}
