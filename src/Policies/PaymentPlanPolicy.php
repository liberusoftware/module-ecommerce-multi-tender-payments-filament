<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\OperatorContext;

/**
 * Every ability Filament can ask about a payment plan, answered by name.
 *
 * A model with no policy is exposed rather than safe, and a policy that is
 * present but missing the method Filament asked about is *also* exposed —
 * `Filament\get_authorization_response()` returns an allowance in exactly that
 * case. So nothing here is left out and nothing is inherited: the list below is
 * the complete set of abilities Filament's resource and relation-manager layers
 * consult, and each one has a method.
 *
 * Only reading is discretionary. Everything else is refused structurally,
 * because a plan is opened by the domain when an order needs one — its currency
 * comes from the resolved payable total and from nowhere else — so a plan
 * created in a panel would be a plan with a currency somebody typed.
 *
 * Every method takes only the actor. Laravel passes the record as a further
 * argument for the abilities that have one and passes nothing extra for the
 * abilities that do not; a signature naming only the parameter that is always
 * present is correct for both, and cannot break when Filament asks about an
 * ability in the form that carries no record.
 *
 * A non-nullable {@see Authenticatable} is what makes a guest a denial: the
 * gate declines to call a policy method it cannot supply a user for.
 */
final class PaymentPlanPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return OperatorContext::grants(Ability::View);
    }

    public function view(Authenticatable $user): bool
    {
        return OperatorContext::grants(Ability::View);
    }

    /** A plan's currency comes from the resolved payable total. A panel cannot supply one. */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user): bool
    {
        return false;
    }

    public function delete(Authenticatable $user): bool
    {
        return false;
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDelete(Authenticatable $user): bool
    {
        return false;
    }

    public function forceDeleteAny(Authenticatable $user): bool
    {
        return false;
    }

    public function restore(Authenticatable $user): bool
    {
        return false;
    }

    public function restoreAny(Authenticatable $user): bool
    {
        return false;
    }

    public function replicate(Authenticatable $user): bool
    {
        return false;
    }

    public function reorder(Authenticatable $user): bool
    {
        return false;
    }
}
