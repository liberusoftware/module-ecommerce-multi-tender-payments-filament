# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-12

First release. A Filament operator surface for
`liberusoftware/ecommerce-multi-tender-payments`. It presents that package and
adds no behaviour of its own.

### Added

- `MultiTenderPaymentsPlugin`, attached per panel by the host. Nothing in this
  package registers globally, and discovery is pointed at this package's own
  resource namespace and nothing wider.
- `PaymentPlanResource`, read-only: an index page and a view page, and no create
  page, no edit page, no delete action, no bulk actions.
- A tender ledger relation manager that is read-only in Filament's own sense, so
  associate, attach, detach, dissociate, create, edit, delete, replicate,
  restore and reorder are closed without a policy being consulted at all.
- A reversal action, the one write this surface offers. It appends a new ledger
  entry through the domain's `ReverseTender` and lets that action be the only
  authority on whether the reversal is allowed.
- `PaymentPlanPolicy` and `TenderEntryPolicy`, registered globally by the
  service provider, naming every ability Filament can ask about — including
  `associate`, `attach`, `detach` and `dissociate`, which default open on a
  `hasMany` and which a policy that merely omits them leaves open.
- Three host-granted gate abilities: `…​.view`, `…​.reverse` and
  `…​.view-gateway-reference`. All three deny until the host defines them.
- Outstanding balances rendered by calling the domain's `OutstandingBalance`
  fold on every render. No stored balance is read, written or cached.
- An `Unknown` balance rather than a zero when `ResolvesPayableTotal` is unbound
  or answers null.
- `PanelContextUnavailable`, raised when the plugin is attached to a panel with
  tenancy enabled, because the domain publishes no tenant column to scope by.

[0.1.0]: https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/releases/tag/0.1.0
