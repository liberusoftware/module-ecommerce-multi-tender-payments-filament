<?php

declare(strict_types=1);

use Liberu\Ecommerce\MultiTenderPayments\Filament\MultiTenderPaymentsPlugin;

/*
 * The four modules that exist around multi-tender payments, asserted absent by
 * name.
 *
 * Multi-tender payments owns the plan and the arithmetic. It never moves money
 * and it never holds a balance, so the surface presenting it must not reach for
 * the gateway that captured, the card that carried a balance, the refund that
 * decided money was owed back, or the order that knew the total. Each of those
 * belongs to a module of its own, and importing one here would drag it into
 * every composition that wanted only the operator screen.
 */

$forbidden = [
    'PaymentOperations',
    'GiftCardsAndStoreCredit',
    'Refunds',
    'Orders',
];

it('imports no namespace of a neighbouring ecommerce module', function (string $module): void {
    foreach (sourceFiles() as $file) {
        expect((string) file_get_contents($file))
            ->not->toContain('Liberu\\Ecommerce\\'.$module.'\\');
    }
})->with($forbidden);

it('requires no neighbouring ecommerce package', function (string $package): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['require'])->not->toHaveKey('liberusoftware/'.$package)
        ->and($composer['require-dev'])->not->toHaveKey('liberusoftware/'.$package);
})->with([
    'ecommerce-payment-operations',
    'ecommerce-gift-cards-and-store-credit',
    'ecommerce-refunds',
    'ecommerce-orders',
]);

it('depends on exactly one Liberu package, the domain it presents', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    $liberu = array_keys(array_filter(
        $composer['require'],
        static fn (string $constraint, string $package): bool => str_starts_with($package, 'liberusoftware/'),
        ARRAY_FILTER_USE_BOTH,
    ));

    expect($liberu)->toBe(['liberusoftware/ecommerce-multi-tender-payments']);
});

it('reaches the domain package through a VCS repository entry, because it is not on Packagist', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);

    expect($composer['repositories'])->toBe([[
        'type' => 'vcs',
        'url' => 'https://github.com/liberusoftware/module-ecommerce-multi-tender-payments',
    ]]);
});

it('never reaches into the host application', function (): void {
    foreach (sourceFiles() as $file) {
        expect((string) file_get_contents($file))
            ->not->toMatch('/(?:use|new|extends|implements)\s+App\\\\/');
    }
});

it('re-derives no money figure of its own', function (): void {
    // Money arrives as integer minor units and leaves as the domain's own
    // decimal string. A float cast, a division or a rounding call in this
    // package would be a second, worse copy of arithmetic the domain already
    // did exactly.
    foreach (sourceFiles() as $file) {
        $source = (string) file_get_contents($file);

        expect($source)->not->toContain('number_format(')
            ->and($source)->not->toContain('round(')
            ->and($source)->not->toContain('(float)')
            ->and($source)->not->toContain('floatval');
    }
});

it('names its plugin the same thing everywhere', function (): void {
    $root = dirname(__DIR__, 2);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);
    $manifest = json_decode((string) file_get_contents($root.'/module.json'), true);

    expect(MultiTenderPaymentsPlugin::ID)
        ->toBe($manifest['name'])
        ->toBe($composer['extra']['liberu']['name'])
        ->and($manifest['presentation']['filament']['admin'])
        ->toBe([MultiTenderPaymentsPlugin::class]);
});
