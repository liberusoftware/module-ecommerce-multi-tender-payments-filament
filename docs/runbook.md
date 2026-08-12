# Runbook

What each failure on the operator screen means, and what to do about it.

## The outstanding balance shows `Unknown`

Two different causes, and they need different people.

**The host bound no `ResolvesPayableTotal`.** A deployment fault. Every plan on
the screen will say `Unknown`, because the module was never told what any order
is worth. Bind the contract — see [adoption.md](adoption.md) §4. It is not a
data problem and nothing is wrong with the plans.

**The resolver answered null for this order.** A fact about one order, so only
that row says `Unknown`. The order reference on the plan does not resolve to a
total in whatever the host's resolver reads. Either the order was deleted, or
the reference was written by something that had a different idea of what an
order reference is.

Neither ever renders as `0.00`. A zero balance reads as *settled*, and the
module was told nothing.

## The page raises `PanelContextUnavailable`

The plugin is attached to a panel with tenancy enabled. The domain package holds
no tenant column, so there is nothing to scope the query by and the surface
refuses to render rather than list every business's plans to whoever is current.

Attach the plugin to an untenanted panel, or partition at the resolver seam the
domain publishes. Do not disable the check.

## "The reversal was refused"

The domain declined, and its message says which of the three reasons applies.

- **Only a captured tender can be reversed.** The entry is declined or is itself
  a reversal. A declined tender moved no money; there is nothing to undo.
- **Already reversed.** A tender is reversed once. The reversal is already in
  the ledger — it is the row beneath.
- **A reversal must carry a reason.** The reason box was empty or whitespace.

The field is not marked required on purpose: that rule belongs to the domain,
and restating it as form validation would make a copy free to drift from the
original.

## The reversal action is not there at all

The host has not granted `ecommerce-multi-tender-payments.reverse` to this
operator. Reading and reversing are separate grants so that a reconciliation
clerk can have the queue without the ability to append to it.

## The gateway reference column is missing

Same shape: `ecommerce-multi-tender-payments.view-gateway-reference` has not
been granted. It is separate from the read grant because most people working a
reconciliation queue need the ledger and do not need the gateway's identifiers
for it.

The column is also never searchable and never filterable, for anyone. A search
term and a filter state both persist into the query string, and from there into
a browser history, a proxy log and any link somebody pastes into a chat.

## The queue is empty for one operator and full for another

Check the `ecommerce-multi-tender-payments.view` grant. An operator without it
cannot access the resource at all — Filament hides the navigation item and the
page returns 403 — rather than seeing an empty table. An empty table means there
are no plans.

## Somebody wants to correct a tender

They cannot, and no configuration change will let them.

The tender ledger is append-only. The entry describes a movement of money at an
institution outside this fleet, and editing the row would change this system's
account of the world without changing the world. If the movement was undone at
the institution, record a reversal — that is what a reversal is for. If the
entry was written wrong by whatever integration wrote it, the fix belongs in
that integration and the incorrect entry stays, because it is a record of what
this system was told.

## Somebody wants to add a tender by hand

They cannot either, for the same shape of reason. A tender records what an
institution did. An entry typed into a panel records nothing that happened, and
it would move the outstanding balance of a real order.

If a payment genuinely arrived outside every integration — a bank transfer
somebody reconciled by hand — the module that observed it records it through the
domain's `RecordTender`, with the reference whoever observed it can produce. A
panel is not that module.

## A reversal did not produce a refund

It is not meant to. Recording that a tender was reversed is a ledger entry in
this module. Deciding that money is owed back to a customer is
`ecommerce-refunds`, and the two are separate decisions on purpose — a
chargeback reverses a tender and creates no refund at all.
