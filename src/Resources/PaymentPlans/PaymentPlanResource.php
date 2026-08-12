<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages\ListPaymentPlans;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\RelationManagers\TendersRelationManager;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\OperatorContext;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\PlanBalance;
use Liberu\Ecommerce\MultiTenderPayments\Models\PaymentPlan;

/**
 * The reconciliation queue: every plan, and what each still owes.
 *
 * Read-only by construction. There is no create page, no edit page and no
 * delete action, and the `can*` overrides below say so a second time — a policy
 * can be reopened by a host's `Gate::before` callback, and a page that does not
 * exist cannot be. Both guards are deliberate; either alone has a hole.
 *
 * The outstanding balance is a column of computed text, not a column of the
 * table. There is no balance to read: the domain folds the ledger on every
 * render. That is also why the balance is neither sortable nor searchable —
 * sorting by it would mean a database expression over a figure the database
 * does not have.
 */
class PaymentPlanResource extends Resource
{
    protected static ?string $model = PaymentPlan::class;

    protected static ?string $slug = 'multi-tender-payment-plans';

    protected static ?string $recordTitleAttribute = 'order_reference';

    protected static ?string $modelLabel = 'payment plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Payment plans';

    /**
     * Refuse to read anything at all when the panel expects a tenant column.
     *
     * Every read path on this resource funnels through here, which is why the
     * check lives here rather than in each page.
     */
    public static function getEloquentQuery(): Builder
    {
        OperatorContext::assertPlansAreScopable();

        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_reference')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Currency'),
                // Computed, every render, by folding the ledger. Never stored,
                // never cached and never sorted on.
                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->state(fn (PaymentPlan $record): string => PlanBalance::label($record)),
                TextColumn::make('created_at')
                    ->label('Opened')
                    ->dateTime(timezone: OperatorContext::timezone())
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('order_reference')->label('Order'),
            TextEntry::make('currency')->label('Currency'),
            TextEntry::make('outstanding')
                ->label('Outstanding')
                ->state(fn (PaymentPlan $record): string => PlanBalance::label($record)),
            TextEntry::make('created_at')
                ->label('Opened')
                ->dateTime(timezone: OperatorContext::timezone()),
        ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            TendersRelationManager::class,
        ];
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListPaymentPlans::route('/'),
            'view' => ViewPaymentPlan::route('/{record}'),
        ];
    }

    /**
     * A plan's currency comes from the payable total the host resolved. A panel
     * has no total, so it cannot open a plan.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function canReplicate(Model $record): bool
    {
        return false;
    }

    public static function canReorder(): bool
    {
        return false;
    }
}
