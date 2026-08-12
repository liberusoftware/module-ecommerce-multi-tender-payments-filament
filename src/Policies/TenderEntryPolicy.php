<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\Ability;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Support\OperatorContext;

/**
 * Every ability Filament can ask about a tender ledger entry, answered by name.
 *
 * Two rules from the domain decide almost all of it.
 *
 * **The ledger is append-only.** There is no update path and no delete path on
 * a recorded tender — the model itself throws on both — because the movement of
 * money an entry describes happened at an institution this fleet does not
 * control, and editing the row would be a lie about the world. `update`,
 * `delete`, `deleteAny`, `forceDelete`, `forceDeleteAny`, `restore`,
 * `restoreAny`, `replicate` and `reorder` are therefore false, always, for
 * everyone.
 *
 * **A tender no gateway ever saw cannot be invented.** `create` is false for the
 * same reason: an entry records what an institution did, and an operator typing
 * one into a panel records nothing that happened.
 *
 * The four abilities below `create` are the ones that catch people. Filament's
 * relation-manager layer asks about `associate`, `attach`, `detach` and
 * `dissociate` (and their `*Any` forms) on a `hasMany`, they default open, and a
 * policy that simply omits them is read as an allowance. Attaching an existing
 * entry to a different plan is the same fault as inventing one — it moves money
 * from one order to another by editing a foreign key — so all eight are named
 * and refused.
 *
 * That leaves reading and recording a reversal, both of which are the host's
 * decision, and the gate denies each until the host says otherwise. Whether a
 * gateway reference is shown is a third such decision, but it is not a Filament
 * ability and no Filament code path ever asks for it — the column asks
 * {@see OperatorContext} directly, so there is no policy method here to be
 * mistaken for one Filament consults.
 *
 * `reverse` grants the *operator's* permission and nothing more. Whether a
 * particular tender may be reversed — captured, not already reversed, carrying
 * a reason — belongs to the domain's `ReverseTender`, which is the only
 * authority on it. Restating those conditions here would be a second copy of an
 * invariant, free to drift from the first.
 */
final class TenderEntryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return OperatorContext::grants(Ability::View);
    }

    public function view(Authenticatable $user): bool
    {
        return OperatorContext::grants(Ability::View);
    }

    /** Not one of Filament's own abilities. The domain still decides whether the reversal happens. */
    public function reverse(Authenticatable $user): bool
    {
        return OperatorContext::grants(Ability::Reverse);
    }

    /** An entry records what an institution did. A panel has done nothing. */
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

    public function associate(Authenticatable $user): bool
    {
        return false;
    }

    public function attach(Authenticatable $user): bool
    {
        return false;
    }

    public function detach(Authenticatable $user): bool
    {
        return false;
    }

    public function detachAny(Authenticatable $user): bool
    {
        return false;
    }

    public function dissociate(Authenticatable $user): bool
    {
        return false;
    }

    public function dissociateAny(Authenticatable $user): bool
    {
        return false;
    }
}
