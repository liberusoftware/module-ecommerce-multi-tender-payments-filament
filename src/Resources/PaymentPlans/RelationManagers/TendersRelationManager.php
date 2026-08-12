<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Liberu\Ecommerce\MultiTenderPayments\Actions\ReverseTender;
use Liberu\Ecommerce\MultiTenderPayments\Exceptions\CannotReverseTender;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\OperatorContext;
use Liberu\Ecommerce\MultiTenderPayments\Models\TenderEntry;

/**
 * The append-only ledger, in the order it was appended.
 *
 * What is missing here is the design. There is no create action, so a tender no
 * gateway ever saw cannot be typed in. There is no edit action and no delete
 * action, because an entry describes a movement of money at an institution this
 * fleet does not control and editing the row would be a lie about the world.
 * There is no associate, attach, detach or dissociate action either — those
 * default open on a `hasMany` and would move an entry from one order's plan to
 * another's by rewriting a foreign key.
 *
 * {@see self::isReadOnly()} closes the same set a second way. Filament refuses
 * associate, attach, detach, dissociate, create, edit, delete, replicate,
 * restore and reorder on a read-only relation manager without consulting a
 * policy at all, so the guarantee survives a host whose `Gate::before` callback
 * answers yes to everything for an administrator.
 *
 * The one thing an operator can do is record a reversal, which appends a new
 * entry rather than altering the one it reverses.
 */
class TendersRelationManager extends RelationManager
{
    protected static string $relationship = 'tenders';

    protected static ?string $title = 'Tender ledger';

    /** Nothing on this relationship may be written through Filament's own machinery. */
    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->label('#'),
                // The domain's enums are plain backed enums — framework-free, by
                // design — so their value is spelled out rather than left to a
                // formatter that expects Filament's HasLabel.
                TextColumn::make('kind')
                    ->label('Kind')
                    ->state(fn (TenderEntry $record): string => $record->kind->value)
                    ->badge(),
                TextColumn::make('effect')
                    ->label('Effect')
                    ->state(fn (TenderEntry $record): string => $record->effect->value)
                    ->badge(),
                // Money is rendered from the domain's own decimal string, which
                // is integer minor units turned into digits by string
                // arithmetic. Nothing here divides or rounds.
                TextColumn::make('amount')
                    ->label('Amount')
                    ->state(fn (TenderEntry $record): string => $record->amount()->decimal().' '.$record->plan->currency),
                TextColumn::make('requested_minor')
                    ->label('Partly spent')
                    ->state(fn (TenderEntry $record): string => $record->isPartlySpent() ? 'Yes' : 'No'),
                // A gateway's identifier is behind its own ability, and is
                // neither searchable nor filterable: a search term and a filter
                // both persist into the query string, where a reference nobody
                // was cleared to see would then sit in a browser history, a
                // proxy log and a shared link.
                TextColumn::make('external_reference')
                    ->label('Gateway reference')
                    ->visible(fn (): bool => OperatorContext::grants(Ability::ViewGatewayReference)),
                TextColumn::make('reason')
                    ->label('Reason'),
                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime(timezone: OperatorContext::timezone()),
            ])
            ->defaultSort('id')
            ->recordActions([
                $this->reverseAction(),
            ]);
    }

    /**
     * Append a reversal.
     *
     * The panel does not pre-judge whether this entry can be reversed. Only a
     * captured tender may be, only once, and only with a reason — and all three
     * of those are the domain's invariants, held in `ReverseTender`. Restating
     * them as form validation or as a visibility condition would create a
     * second copy free to drift from the first, so the refusal the domain
     * raises is what the operator is shown.
     *
     * The reason field is deliberately not marked required for the same reason:
     * "a reversal must carry a reason" is a rule the domain owns.
     */
    private function reverseAction(): Action
    {
        return Action::make('reverse')
            ->label('Record reversal')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Record a reversal')
            ->modalDescription(
                'This appends a reversal to the ledger. It does not move money back, and it does not '
                .'create a refund — what is owed to a customer is decided elsewhere.'
            )
            ->authorize('reverse')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->helperText('Why this tender was reversed. It is recorded on the new ledger entry.'),
            ])
            ->action(function (TenderEntry $record, array $data, Action $action): void {
                try {
                    app(ReverseTender::class)($record, (string) ($data['reason'] ?? ''));
                } catch (CannotReverseTender $exception) {
                    Notification::make()
                        ->danger()
                        ->title('The reversal was refused')
                        ->body($exception->getMessage())
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title('Reversal recorded')
                    ->send();
            });
    }
}
