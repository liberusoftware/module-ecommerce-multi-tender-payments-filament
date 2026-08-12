# What this surface presents

This package owns no domain. Every rule below belongs to
`liberusoftware/ecommerce-multi-tender-payments`; what is written here is how
each one shows up on a screen, and what this package refuses to do about it.

## The one sentence the whole design follows

> **There is no transaction across gateways.**

A three-tender plan is three separate movements of real money, at three separate
institutions, at three separate instants. When tender two declines, tender one's
capture has already happened and no application-level rollback can un-happen it.

That is why the ledger is append-only, why a declined tender is *shown* rather
than hidden, and why the only write this surface offers appends a new row.

## The screens

### The queue — `PaymentPlanResource`

Every plan, its currency, when it was opened, and what it still owes.

The outstanding column is computed text, not a column of the table. There is no
balance stored anywhere: `OutstandingBalance` folds the ledger every time the
row is rendered, so a reversal recorded a second ago is already reflected
without anything having been updated.

That is also why the column is neither sortable nor searchable. Sorting would
mean a database expression over a figure the database does not have.

One column is searchable: `order_reference`. A search term persists into the
query string, and the order reference is the handle an operator was already
given by the person asking about the order.

### The plan — `ViewPaymentPlan`

The same four facts, and the ledger beneath.

No header actions. There is no edit page to link to and no delete action to
offer.

### The ledger — `TendersRelationManager`

Every entry in the order it was appended, including the ones that moved nothing.
A declined tender is recorded because it happened; it does not erase,
invalidate or roll back an earlier captured one, and the operator has to see it.

Columns: position, kind, effect, amount, whether the tender was partly spent,
the gateway reference (behind its own ability), the reason on a reversal, and
when it occurred.

`isReadOnly()` returns true. Filament refuses associate, attach, detach,
dissociate, create, edit, delete, replicate, restore and reorder on a read-only
relation manager without consulting a policy at all, which is a guarantee that
survives a host whose `Gate::before` answers yes to everything for an
administrator.

## Money

Integer minor units, end to end, and this package does nothing to them.

An amount is rendered as `Money::decimal()` — the domain turning integer minor
units into digits by string arithmetic — followed by the plan's currency code.
There is no `number_format`, no division, no rounding and no float cast
anywhere in `src/`, and a test greps for each of them.

`(int) (19.99 * 100)` is `1998`. The domain holds `1999` and prints `19.99`
back; this surface reads that string and shows it.

## Partial payment is the ordinary case

A gift card covering 40% of a total contributes 40% and the remainder becomes
outstanding. The ledger marks that entry partly spent, because the domain keeps
both what was asked of a tender and what it could give.

There is no "plan failed" state to display. A plan is satisfied or it has an
outstanding balance; those are the only two answers and both are computed.

## Deposits and instalments

A deposit is an ordinary tender recorded before the order is complete, folded
into the same balance as everything else. There is no separate deposit screen
because there is no separate deposit ledger.

An instalment is an external reference and nothing more. This surface will not
tell an operator what is due when, because the domain holds no authoritative due
date and runs no scheduler.

## Reversal is not refund

Appending a reversal says a tender was undone. It does not say money is owed
back to a customer — that is `ecommerce-refunds`, and nothing here creates one
there. The action's own modal says so.

## What this surface does not decide

- **Whether a reversal is allowed.** Only a captured tender, only once, only
  with a reason — all three live in `ReverseTender`. The reason field is not
  marked required, and no visibility condition inspects the entry's effect,
  because either would be a second copy of an invariant that could drift from
  the first.
- **What an order is worth.** `ResolvesPayableTotal` is the host's, and it is
  registered with no default binding on purpose.
- **What a tender is worth.** `ResolvesTenderCapacity` is the host's too. This
  surface never asks what a gift card holds.
- **What a tenant is.** There is no tenant column. See
  [runbook.md](runbook.md).
