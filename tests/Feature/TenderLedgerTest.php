<?php

declare(strict_types=1);

use Filament\Actions\AssociateAction;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Column;
use Liberu\Ecommerce\MultiTenderPayments\Enums\TenderEffect;
use Liberu\Ecommerce\MultiTenderPayments\Enums\TenderKind;
use Liberu\Ecommerce\MultiTenderPayments\Exceptions\TenderLedgerIsAppendOnly;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\PlanBalance;
use Liberu\Ecommerce\MultiTenderPayments\Models\TenderEntry;

describe('reading the ledger', function (): void {
    it('lists every entry, including the ones that moved nothing', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        $captured = entry($plan, 4_000, TenderKind::GiftCard, position: 0);
        $declined = entry($plan, 6_000, TenderKind::Card, TenderEffect::Declined, position: 1);

        // A declined tender is recorded because it happened. It does not erase
        // or roll back the capture before it, and the operator has to see it.
        ledger($plan)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$captured, $declined]);
    });

    it('is read-only, which closes Filament\'s own write paths without consulting a policy', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');

        expect(ledger($plan)->instance()->isReadOnly())->toBeTrue();
    });
});

describe('the ledger is append-only', function (): void {
    it('offers no action that would edit, destroy or re-parent an entry', function (): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        entry($plan, 4_000);

        $table = ledger($plan)->instance()->getTable();

        $actions = [
            ...$table->getHeaderActions(),
            ...$table->getRecordActions(),
            ...$table->getToolbarActions(),
        ];

        foreach ($actions as $action) {
            expect($action)->not->toBeInstanceOf(CreateAction::class)
                ->not->toBeInstanceOf(EditAction::class)
                ->not->toBeInstanceOf(DeleteAction::class)
                ->not->toBeInstanceOf(DeleteBulkAction::class)
                ->not->toBeInstanceOf(ForceDeleteAction::class)
                ->not->toBeInstanceOf(RestoreAction::class)
                ->not->toBeInstanceOf(ReplicateAction::class)
                ->not->toBeInstanceOf(AssociateAction::class)
                ->not->toBeInstanceOf(AttachAction::class)
                ->not->toBeInstanceOf(DetachAction::class)
                ->not->toBeInstanceOf(DissociateAction::class);
        }

        // The only thing on a row is the reversal, which appends.
        expect(array_map(
            static fn (object $action): string => $action->getName(),
            $table->getRecordActions(),
        ))->toBe(['reverse']);
    });

    it('is refused by the model itself, which is the guarantee the panel only mirrors', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        $tender = entry($plan, 4_000);

        expect(fn () => $tender->update(['amount_minor' => 1]))
            ->toThrow(TenderLedgerIsAppendOnly::class);
    });
});

describe('a tender no gateway saw cannot be invented', function (): void {
    it('refuses every ability that would create or re-parent an entry', function (string $ability): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        entry($plan, 4_000);

        $manager = ledger($plan)->instance();

        $method = new ReflectionMethod($manager, $ability);

        expect($method->invoke($manager))->toBeFalse();
    })->with([
        'canCreate',
        'canAssociate',
        'canAttach',
        'canDeleteAny',
        'canDetachAny',
        'canDissociateAny',
        'canReorder',
        'canRestoreAny',
        'canForceDeleteAny',
    ]);

    it('refuses every per-record ability that would rewrite an entry', function (string $ability): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        $tender = entry($plan, 4_000);

        $manager = ledger($plan)->instance();

        $method = new ReflectionMethod($manager, $ability);

        expect($method->invoke($manager, $tender))->toBeFalse();
    })->with([
        'canEdit',
        'canDelete',
        'canDetach',
        'canDissociate',
        'canForceDelete',
        'canReplicate',
        'canRestore',
    ]);
});

describe('recording a reversal', function (): void {
    it('appends a new entry and leaves the original exactly where it was', function (): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1', total: 10_000);
        $captured = entry($plan, 4_000);

        expect(PlanBalance::label($plan))->toBe('60.00 GBP');

        ledger($plan)->callAction(
            TestAction::make('reverse')->table($captured),
            ['reason' => 'Chargeback raised by the issuer.'],
        );

        $reversal = TenderEntry::query()->where('reverses_tender_id', $captured->id)->sole();

        expect($reversal->effect)->toBe(TenderEffect::Reversed)
            ->and($reversal->reason)->toBe('Chargeback raised by the issuer.')
            ->and($captured->fresh()->effect)->toBe(TenderEffect::Captured)
            ->and($captured->fresh()->amount_minor)->toBe(4_000)
            ->and(PlanBalance::label($plan))->toBe('100.00 GBP');
    });

    it('hides the reversal from an operator the host did not grant it to', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        $captured = entry($plan, 4_000);

        ledger($plan)->assertActionHidden(TestAction::make('reverse')->table($captured));
    });

    it('lets the domain refuse a reason-less reversal, rather than restating the rule as validation', function (): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        $captured = entry($plan, 4_000);

        // "A reversal must carry a reason" is the domain's invariant. Marking
        // the field required here would make a second copy of it, free to
        // drift; the refusal the operator sees is the domain's own.
        ledger($plan)
            ->callAction(TestAction::make('reverse')->table($captured), ['reason' => '   '])
            ->assertHasNoActionErrors();

        expect(TenderEntry::query()->where('reverses_tender_id', $captured->id)->exists())->toBeFalse();

        // One assertion only: reading the bag empties it.
        Notification::assertNotified('The reversal was refused');
    });

    it('lets the domain refuse a second reversal of the same tender', function (): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        $captured = entry($plan, 4_000);

        ledger($plan)->callAction(
            TestAction::make('reverse')->table($captured),
            ['reason' => 'First reversal.'],
        );

        ledger($plan)->callAction(
            TestAction::make('reverse')->table($captured),
            ['reason' => 'Second reversal.'],
        );

        expect(TenderEntry::query()->where('reverses_tender_id', $captured->id)->count())->toBe(1);
    });

    it('records no refund anywhere, because a reversal is not one', function (): void {
        operator(Ability::View, Ability::Reverse);
        $plan = plan('order-1');
        $captured = entry($plan, 4_000);

        ledger($plan)->callAction(
            TestAction::make('reverse')->table($captured),
            ['reason' => 'Chargeback raised by the issuer.'],
        );

        // Every row this action produced is a tender ledger entry on this plan.
        // Deciding money is owed back to a customer belongs to another module,
        // and nothing here reaches into it.
        expect(TenderEntry::query()->where('plan_id', $plan->id)->count())->toBe(2);
    });
});

describe('gateway references', function (): void {
    it('hides the gateway reference from an operator without that ability', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        entry($plan, 4_000, externalReference: 'ch_live_secret');

        $table = ledger($plan)->instance()->getTable();

        expect($table->getColumns()['external_reference']->isVisible())->toBeFalse();

        ledger($plan)->assertDontSee('ch_live_secret');
    });

    it('shows it to an operator the host granted it to', function (): void {
        operator(Ability::View, Ability::ViewGatewayReference);
        $plan = plan('order-1');
        entry($plan, 4_000, externalReference: 'ch_live_secret');

        ledger($plan)->assertSee('ch_live_secret');
    });

    it('never makes a gateway reference searchable, because a search term persists into the URL', function (): void {
        operator(Ability::View, Ability::ViewGatewayReference);
        $plan = plan('order-1');
        entry($plan, 4_000);

        $table = ledger($plan)->instance()->getTable();

        $searchable = array_keys(array_filter(
            $table->getColumns(),
            static fn (Column $column): bool => $column->isSearchable(),
        ));

        expect($searchable)->toBe([])
            ->and($table->getFilters())->toBe([]);
    });
});

describe('money on the surface', function (): void {
    it('renders amounts from the domain\'s own decimal string, never re-derived', function (): void {
        operator(Ability::View);
        $plan = plan('order-1');
        // 19.99 is the case that breaks the float route: (int) (19.99 * 100)
        // is 1998. The domain holds 1999 minor units and prints them back by
        // string arithmetic, and this surface only reads that string.
        $tender = entry($plan, 1_999);

        expect($tender->amount()->minor)->toBe(1_999)
            ->and($tender->amount()->decimal())->toBe('19.99');

        ledger($plan)->assertSee('19.99 GBP');
    });

    it('marks a short tender as partly spent rather than as a failure', function (): void {
        operator(Ability::View);
        $plan = plan('order-1', total: 10_000);
        $tender = TenderEntry::query()->create([
            'plan_id' => $plan->id,
            'position' => 0,
            'kind' => TenderKind::GiftCard,
            'effect' => TenderEffect::Captured,
            'amount_minor' => 4_000,
            'requested_minor' => 10_000,
            'occurred_at' => now(),
        ]);

        expect($tender->isPartlySpent())->toBeTrue()
            ->and(PlanBalance::label($plan))->toBe('60.00 GBP');
    });
});
