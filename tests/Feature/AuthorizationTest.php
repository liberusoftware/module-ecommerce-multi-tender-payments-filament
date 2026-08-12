<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Policies\PaymentPlanPolicy;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Policies\TenderEntryPolicy;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Models\PaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Models\TenderEntry;

/*
 * The permissive-gate trap, closed and pinned.
 *
 * A model with no policy is exposed rather than safe. Worse, Filament's
 * `get_authorization_response()` answers *allow* when a policy is present but
 * lacks the method it was asked about, and `canAssociate`/`canDissociate` are
 * live on a `hasMany` and default open. So every ability is named, and every
 * name has a denied path here.
 */

it('answers for both of the domain\'s models, because a model with no policy is exposed', function (): void {
    expect(Gate::getPolicyFor(PaymentPlan::class))->toBeInstanceOf(PaymentPlanPolicy::class)
        ->and(Gate::getPolicyFor(TenderEntry::class))->toBeInstanceOf(TenderEntryPolicy::class);
});

it('leaves no ability Filament asks about unanswered on a plan', function (string $ability): void {
    expect(method_exists(PaymentPlanPolicy::class, $ability))->toBeTrue();
})->with([
    'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
    'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder',
]);

it('leaves no ability Filament asks about unanswered on a tender', function (string $ability): void {
    expect(method_exists(TenderEntryPolicy::class, $ability))->toBeTrue();
})->with([
    'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
    'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny', 'replicate', 'reorder',
    'associate', 'attach', 'detach', 'detachAny', 'dissociate', 'dissociateAny',
]);

it('refuses every write ability on a plan even to an operator granted everything', function (string $ability): void {
    $user = operator(Ability::View, Ability::Reverse, Ability::ViewGatewayReference);
    $plan = plan('order-1');

    expect(Gate::forUser($user)->allows($ability, $plan))->toBeFalse();
})->with([
    'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny',
    'restore', 'restoreAny', 'replicate', 'reorder',
]);

it('refuses every write ability on a tender even to an operator granted everything', function (string $ability): void {
    $user = operator(Ability::View, Ability::Reverse, Ability::ViewGatewayReference);
    $plan = plan('order-1');
    $tender = entry($plan, 4_000);

    expect(Gate::forUser($user)->allows($ability, $tender))->toBeFalse();
})->with([
    'create', 'update', 'delete', 'deleteAny', 'forceDelete', 'forceDeleteAny',
    'restore', 'restoreAny', 'replicate', 'reorder',
    'associate', 'attach', 'detach', 'detachAny', 'dissociate', 'dissociateAny',
]);

it('refuses reading to an operator the host granted nothing', function (string $ability): void {
    $user = operator();
    $plan = plan('order-1');
    $tender = entry($plan, 4_000);

    expect(Gate::forUser($user)->allows($ability, $plan))->toBeFalse()
        ->and(Gate::forUser($user)->allows($ability, $tender))->toBeFalse();
})->with(['viewAny', 'view']);

it('grants reading only once the host has defined the ability', function (): void {
    $user = operator(Ability::View);
    $plan = plan('order-1');

    expect(Gate::forUser($user)->allows('viewAny', $plan))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $plan))->toBeTrue();
});

it('refuses the reversal until the host grants it, separately from reading', function (): void {
    $reader = operator(Ability::View);
    $plan = plan('order-1');
    $tender = entry($plan, 4_000);

    expect(Gate::forUser($reader)->allows('reverse', $tender))->toBeFalse();
});

it('grants the reversal to an operator the host granted it to', function (): void {
    $user = operator(Ability::View, Ability::Reverse);
    $plan = plan('order-1');
    $tender = entry($plan, 4_000);

    expect(Gate::forUser($user)->allows('reverse', $tender))->toBeTrue();
});

it('refuses everything to a guest, because a policy method cannot be called without a user', function (string $ability): void {
    // The abilities are defined and would say yes; the gate never gets as far
    // as asking, because every policy method here names a non-nullable actor.
    Gate::define(Ability::View->value, fn (): bool => true);
    Gate::define(Ability::Reverse->value, fn (): bool => true);

    $plan = plan('order-1');
    $tender = entry($plan, 4_000);

    expect(Gate::forUser(null)->allows($ability, $plan))->toBeFalse()
        ->and(Gate::forUser(null)->allows($ability, $tender))->toBeFalse();
})->with(['viewAny', 'view', 'create', 'update', 'delete', 'reverse']);
