<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\MultiTenderPayments\Contracts\ResolvesPayableTotal;
use Liberu\Ecommerce\MultiTenderPayments\Enums\TenderEffect;
use Liberu\Ecommerce\MultiTenderPayments\Enums\TenderKind;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\RelationManagers\TendersRelationManager;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Tests\Fixtures\FakeHost;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Tests\TestCase;
use Liberu\Ecommerce\MultiTenderPayments\Models\PaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Models\TenderEntry;
use Liberu\Ecommerce\MultiTenderPayments\Support\Money;
use Liberu\PackageTestbench\TestUser;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * An authenticated operator holding exactly the abilities named and no others.
 *
 * Nothing is granted by default, which is the point: the gate's answer to a
 * question nobody defined is a denial, and every allowed path in this suite has
 * to say out loud which grant made it allowed.
 */
function operator(Ability ...$abilities): Authenticatable
{
    foreach ($abilities as $ability) {
        Gate::define($ability->value, fn (Authenticatable $user): bool => true);
    }

    $user = TestUser::factory()->create();

    test()->actingAs($user);

    return $user;
}

/** Bind the payable-total resolver the domain publishes and never implements. */
function host(): FakeHost
{
    $host = new FakeHost();

    app()->instance(ResolvesPayableTotal::class, $host);

    return $host;
}

/** A plan whose order the host can price, which is the ordinary case. */
function plan(string $order = 'order-1', ?int $total = 10_000, string $currency = 'GBP'): PaymentPlan
{
    host()->total($order, $total === null ? null : new Money($total, $currency));

    return PaymentPlan::query()->create([
        'order_reference' => $order,
        'currency' => $currency,
        'currency_exponent' => 2,
    ]);
}

/** One entry appended to a plan's ledger. */
function entry(
    PaymentPlan $plan,
    int $amount,
    TenderKind $kind = TenderKind::Card,
    TenderEffect $effect = TenderEffect::Captured,
    int $position = 0,
    ?string $externalReference = 'gw_1',
): TenderEntry {
    return TenderEntry::query()->create([
        'plan_id' => $plan->id,
        'position' => $position,
        'kind' => $kind,
        'effect' => $effect,
        'amount_minor' => $effect === TenderEffect::Captured ? $amount : 0,
        'requested_minor' => $amount,
        'external_reference' => $externalReference,
        'occurred_at' => Carbon::now(),
    ]);
}

/** The tender ledger relation manager, mounted the way the view page mounts it. */
function ledger(PaymentPlan $plan): Testable
{
    return Livewire::test(TendersRelationManager::class, [
        'ownerRecord' => $plan,
        'pageClass' => ViewPaymentPlan::class,
    ]);
}

/**
 * Every PHP file this package ships, for the boundary rules to read.
 *
 * @return list<string>
 */
function sourceFiles(): array
{
    $files = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/src')) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
