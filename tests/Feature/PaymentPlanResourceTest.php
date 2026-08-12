<?php

declare(strict_types=1);

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Liberu\Ecommerce\MultiTenderPayments\Contracts\ResolvesPayableTotal;
use Liberu\Ecommerce\MultiTenderPayments\Enums\TenderEffect;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Exceptions\PanelContextUnavailable;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages\ListPaymentPlans;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\PaymentPlanResource;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\PlanBalance;
use Liberu\Ecommerce\MultiTenderPayments\Queries\OutstandingBalance;
use Liberu\PackageTestbench\TestUser;
use Livewire\Livewire;

describe('reading the queue', function (): void {
    it('lists the plans an operator is allowed to read', function (): void {
        operator(Ability::View);
        $first = plan('order-1');
        $second = plan('order-2');

        Livewire::test(ListPaymentPlans::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$first, $second]);
    });

    it('renders one plan, its balance and its ledger', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        entry($plan, 4_000);

        Livewire::test(ViewPaymentPlan::class, ['record' => $plan->getKey()])
            ->assertSuccessful();
    });

    it('refuses the queue to an operator the host granted nothing', function (): void {
        operator();

        expect(PaymentPlanResource::canViewAny())->toBeFalse()
            ->and(PaymentPlanResource::canAccess())->toBeFalse();
    });

    it('refuses the queue to nobody at all', function (): void {
        plan('order-1');

        expect(PaymentPlanResource::canViewAny())->toBeFalse();
    });
});

describe('the outstanding balance is a fold, never a column', function (): void {
    it('shows exactly what the domain query folds', function (): void {
        operator(Ability::View);
        $plan = plan('order-1', total: 10_000);
        entry($plan, 4_000);

        $folded = app(OutstandingBalance::class)->forPlan($plan);

        expect($folded->minor)->toBe(6_000)
            ->and(PlanBalance::label($plan))->toBe('60.00 GBP');
    });

    it('changes when the ledger grows, with no stored total to update', function (): void {
        operator(Ability::View);
        $plan = plan('order-1', total: 10_000);
        $captured = entry($plan, 4_000);

        expect(PlanBalance::label($plan))->toBe('60.00 GBP');

        // A reversal is a new entry. Nothing on the plan is written, and the
        // plan's own columns are the same ones it had a moment ago.
        entry($plan, 4_000, effect: TenderEffect::Reversed);

        expect(PlanBalance::label($plan))->toBe('100.00 GBP')
            ->and($plan->fresh()->only(['order_reference', 'currency', 'currency_exponent']))
            ->toBe(['order_reference' => 'order-1', 'currency' => 'GBP', 'currency_exponent' => 2])
            ->and($captured->fresh()->amount_minor)->toBe(4_000);
    });

    it('reads no balance column, because the plan has none', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');

        expect(array_keys($plan->fresh()->getAttributes()))
            ->not->toContain('outstanding_minor')
            ->not->toContain('amount_paid_minor')
            ->not->toContain('status')
            ->not->toContain('payment_status');
    });

    it('says the balance is unknown rather than zero when the host bound no resolver', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');

        // The domain publishes ResolvesPayableTotal and deliberately leaves it
        // unbound. Such a deployment can read a plan; it cannot be told a total,
        // and rendering zero would say "settled".
        app()->forgetInstance(ResolvesPayableTotal::class);

        expect(PlanBalance::forPlan($plan))->toBeNull()
            ->and(PlanBalance::label($plan))->toBe(PlanBalance::UNKNOWN);

        Livewire::test(ListPaymentPlans::class)
            ->assertSuccessful()
            ->assertSee(PlanBalance::UNKNOWN);
    });

    it('says the balance is unknown when the resolver answers null for this order', function (): void {
        operator(Ability::View);
        $plan = plan('order-1', total: null);

        expect(PlanBalance::forPlan($plan))->toBeNull()
            ->and(PlanBalance::label($plan))->toBe(PlanBalance::UNKNOWN);
    });
});

describe('failing closed on missing context', function (): void {
    it('refuses to read plans at all on a panel with tenancy enabled', function (): void {
        operator(Ability::View);
        plan('order-1');

        // The domain holds no tenant column on purpose. Listing every
        // business's plans under one tenant's heading is the one failure this
        // surface must not have, so it renders nothing instead.
        Filament::getPanel('operator')->tenant(TestUser::class);

        expect(fn () => PaymentPlanResource::getEloquentQuery())
            ->toThrow(PanelContextUnavailable::class);
    });

    it('resolves the actor from the panel rather than from any request input', function (): void {
        $user = operator(Ability::View);

        expect(Filament::getPanel('operator')->auth()->user()?->getAuthIdentifier())
            ->toBe($user->getAuthIdentifier());
    });
});

describe('what the queue does not offer', function (): void {
    it('publishes only an index and a view page', function (): void {
        expect(array_keys(PaymentPlanResource::getPages()))->toBe(['index', 'view']);
    });

    it('refuses every ability that would write a plan', function (string $ability): void {
        operator(Ability::View);
        $plan = plan('order-1');

        $answer = match ($ability) {
            'canCreate', 'canDeleteAny', 'canForceDeleteAny', 'canRestoreAny', 'canReorder' => PaymentPlanResource::{$ability}(),
            default => PaymentPlanResource::{$ability}($plan),
        };

        expect($answer)->toBeFalse();
    })->with([
        'canCreate',
        'canEdit',
        'canDelete',
        'canDeleteAny',
        'canForceDelete',
        'canForceDeleteAny',
        'canRestore',
        'canRestoreAny',
        'canReplicate',
        'canReorder',
    ]);

    it('offers no action that would edit or destroy a plan', function (): void {
        operator(Ability::View);
        plan('order-1');

        $table = Livewire::test(ListPaymentPlans::class)->instance()->getTable();

        $actions = [
            ...$table->getHeaderActions(),
            ...$table->getRecordActions(),
            ...$table->getToolbarActions(),
        ];

        foreach ($actions as $action) {
            expect($action)->not->toBeInstanceOf(EditAction::class)
                ->not->toBeInstanceOf(DeleteAction::class)
                ->not->toBeInstanceOf(DeleteBulkAction::class)
                ->not->toBeInstanceOf(ForceDeleteAction::class)
                ->not->toBeInstanceOf(RestoreAction::class)
                ->not->toBeInstanceOf(ReplicateAction::class);
        }
    });

    it('makes nothing sensitive searchable, and offers no filter at all', function (): void {
        operator(Ability::View);
        plan('order-1');

        $table = Livewire::test(ListPaymentPlans::class)->instance()->getTable();

        $searchable = array_keys(array_filter(
            $table->getColumns(),
            static fn (Column $column): bool => $column->isSearchable(),
        ));

        // A search term persists into the query string, so the only searchable
        // column is the operator's own handle on the work — the order reference
        // they were given by the person asking about it.
        expect($searchable)->toBe(['order_reference'])
            ->and($table->getFilters())->toBe([]);
    });

    it('never sorts on the balance, which is not a column the database has', function (): void {
        operator(Ability::View);
        plan('order-1');

        $table = Livewire::test(ListPaymentPlans::class)->instance()->getTable();

        expect($table->getColumns()['outstanding']->isSortable())->toBeFalse();
    });
});
