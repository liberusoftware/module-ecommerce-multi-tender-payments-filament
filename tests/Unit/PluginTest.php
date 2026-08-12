<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Liberu\Ecommerce\MultiTenderPayments\Filament\MultiTenderPaymentsPlugin;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\PaymentPlanResource;

it('registers under a stable id the panel can look it up by', function (): void {
    $plugin = Filament::getPanel('operator')->getPlugin(MultiTenderPaymentsPlugin::ID);

    expect($plugin)->toBeInstanceOf(MultiTenderPaymentsPlugin::class)
        ->and($plugin->getId())->toBe('ecommerce-multi-tender-payments-filament');
});

it('contributes its resource to the panel it is attached to', function (): void {
    expect(Filament::getPanel('operator')->getResources())
        ->toContain(PaymentPlanResource::class);
});

it('discovers this package\'s namespace and nothing wider', function (): void {
    expect(Filament::getPanel('operator')->getResourceNamespaces())
        ->toBe(['Liberu\\Ecommerce\\MultiTenderPayments\\Filament\\Resources']);
});

it('registers nothing globally, so a panel that does not attach it sees nothing', function (): void {
    // The service provider registers policies and no UI. A panel only gets the
    // operator surface by attaching the plugin, which is the difference between
    // a package that contributes UI and one that imposes it.
    expect(Panel::make()->id('bare')->getResources())->toBe([]);
});
