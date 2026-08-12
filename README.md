# Ecommerce: Multi Tender Payments Filament Module

> This package is the Filament presentation surface for Multi Tender Payments. The authoritative, provider-neutral implementation lives in `liberusoftware/ecommerce-multi-tender-payments`; this package translates its public contracts for a panel and adds no behaviour of its own.

[Software](https://liberusoftware.com) ·
[Hosting](https://liberuhosting.com) ·
[Services](https://liberuservices.com) ·
[Liberu Group](https://liberugroup.com)

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white) ![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white) ![Filament](https://img.shields.io/badge/Filament-5-FDAE4B?logo=laravel&logoColor=white)
[![Latest release](https://img.shields.io/github/v/release/liberusoftware/module-ecommerce-multi-tender-payments-filament?sort=semver)](https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/releases/latest) [![Tests](https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/actions/workflows/tests.yml)

## What this module owns

An operator surface, and nothing else.

> It **reads** payment plans, the tender ledger and outstanding balances, and it
> can **record a reversal**. It cannot invent a tender that no gateway ever saw.

- Fully compatible with **Laravel 13**, **PHP 8.5**, **Filament 5** and **Pest 5**.
- A plugin a host attaches per panel. Nothing registers globally.
- Every Filament ability enumerated by name and denied unless the host grants it.
- No dependency on `ecommerce-payment-operations`,
  `ecommerce-gift-cards-and-store-credit`, `ecommerce-refunds` or
  `ecommerce-orders` — asserted by namespace and by package name.

## What is missing, on purpose

The design of this package is mostly a list of things it does not offer.

| Absent | Why |
| --- | --- |
| A create page and a create action | A tender records what an institution did. An operator typing one in records nothing that happened. A plan's currency comes from the payable total the host resolved, so a panel cannot open one either. |
| An edit page and an `EditAction` | The tender ledger is append-only. The movement of money an entry describes happened somewhere this fleet does not control, and editing the row would be a lie about the world. |
| A `DeleteAction`, bulk or otherwise | Same reason. The domain's model throws on both update and delete; this surface never asks it to. |
| `AssociateAction`, `AttachAction`, `DetachAction`, `DissociateAction` | These default **open** on a `hasMany`. Re-parenting an entry moves money from one order to another by rewriting a foreign key, which is the same fault as inventing one. |
| A stored balance, a status column, an `amount_paid` | There is none to display. The outstanding balance is a fold over the ledger, computed on every render. |
| A sortable balance column | Sorting would mean a database expression over a figure the database does not have. |
| A searchable or filterable gateway reference | A search term and a filter state both persist into the query string, and from there into a browser history, a proxy log and a shared link. |

## How a reversal works here

The one thing an operator can do is append a reversal. It is offered on every
entry, and the *domain* decides whether it happens.

Only a captured tender can be reversed, only once, and only with a reason — and
all three of those are invariants of `ReverseTender` in the domain package.
This surface does not restate any of them as form validation or as a visibility
condition, because a second copy of an invariant is a copy free to drift. The
reason field is not marked required for exactly that reason: the refusal an
operator sees is the domain's own, rendered as a notification.

**A reversal here does not create a refund.** Recording that a tender was
reversed is a ledger entry in the multi-tender module. Deciding that money is
owed back to a customer is `ecommerce-refunds`, and nothing in this package
reaches into it.

## Authorization

A model with no policy is exposed rather than safe. Worse, Filament's
`get_authorization_response()` answers **allow** when a policy is present but
lacks the method it was asked about. So both of the domain's models carry a
policy, registered by this package's service provider, and every ability
Filament can ask about has a method on it.

Most answer `false` for everyone, always — they are structural, not
discretionary. Three decisions are handed back to the host, as gate abilities
that deny until the host defines them:

| Ability | Grants |
| --- | --- |
| `ecommerce-multi-tender-payments.view` | Reading plans, the ledger and balances |
| `ecommerce-multi-tender-payments.reverse` | Appending a reversal |
| `ecommerce-multi-tender-payments.view-gateway-reference` | Seeing the reference the module that moved the money handed back |

```php
Gate::define('ecommerce-multi-tender-payments.view', fn (User $user): bool => $user->hasRole('finance'));
```

The structural refusals are guarded twice: by the policy, and by the absence of
the page or action that would use it. Either alone has a hole — a host's
`Gate::before` callback can answer yes to a policy question, and a page that
does not exist cannot be reached at all.

## Context, and failing closed

- **The actor** comes from the panel's own guard. No authenticated actor means
  every ability answers no.
- **The tenant** does not exist. The domain deliberately holds no tenant or site
  column, so attaching this plugin to a panel with tenancy enabled raises
  `PanelContextUnavailable` rather than listing every business's plans under one
  tenant's heading. Scope at the resolver seam the domain publishes instead.
- **The locale** never touches a money figure. Amounts are rendered from the
  domain's `Money::decimal()` — integer minor units turned into digits by string
  arithmetic. Nothing here divides, rounds or re-derives. Timestamps do vary and
  take the application's configured timezone.
- **An unbound `ResolvesPayableTotal`** renders the balance as `Unknown`, not as
  zero. So does a resolver that answers null for an order. Zero would read as
  "settled", and the module was told nothing.

## Requirements

- **PHP 8.5**
- **Composer 2**
- **Filament 5**
- `liberusoftware/ecommerce-multi-tender-payments` — see below, it is not on Packagist

## Quick start

Neither this package nor the domain package is on Packagist, so the host adds
both VCS repository entries to its own `composer.json` first — Composer honours
`repositories` only from the root manifest:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-multi-tender-payments" },
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament" }
]
```

```bash
composer require liberusoftware/ecommerce-multi-tender-payments-filament
```

Installing boots nothing — there is no `extra.laravel.providers` — and the host
enables the module by name through `MODULES_ENABLED`. Then attach the plugin to
whichever panel should see payment plans:

```php
use Liberu\Ecommerce\MultiTenderPayments\Filament\MultiTenderPaymentsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(MultiTenderPaymentsPlugin::make());
}
```

See [docs/adoption.md](docs/adoption.md) for the resolver bindings the domain
package requires; until they exist, this surface can read a plan and can tell
you nothing about what it owes.

## Documentation

- [docs/domain.md](docs/domain.md) — what this surface presents, and what it refuses to
- [docs/adoption.md](docs/adoption.md) — installing, attaching, binding and granting
- [docs/runbook.md](docs/runbook.md) — what each failure on the screen means
- [Liberu Main Documentation](https://github.com/liberusoftware/documentation)
- [Architecture & Standards Index](https://github.com/liberusoftware/documentation/tree/main/architecture)

## Related Liberu Projects

| Project | Repository | Purpose |
| --- | --- | --- |
| **Boilerplate** | [liberusoftware/boilerplate-laravel](https://github.com/liberusoftware/boilerplate-laravel) | Shared Laravel application foundation and reference composition |
| **CMS** | [liberu-cms/cms-laravel](https://github.com/liberu-cms/cms-laravel) | Structured content, publishing, media, multisite, and headless delivery |
| **CRM** | [liberu-crm/crm-laravel](https://github.com/liberu-crm/crm-laravel) | Customer data, sales, marketing, service, and customer success |
| **Billing** | [liberu-billing/billing-laravel](https://github.com/liberu-billing/billing-laravel) | Products, subscriptions, invoicing, payments, and provisioning |
| **Accounting** | [liberu-accounting/accounting-laravel](https://github.com/liberu-accounting/accounting-laravel) | Ledgers, banking, tax, expenses, close, and financial reporting |
| **Ecommerce** | [liberu-ecommerce/ecommerce-laravel](https://github.com/liberu-ecommerce/ecommerce-laravel) | Catalog, checkout, orders, fulfillment, returns, B2B, and omnichannel commerce |
| **Control Panel** | [liberu-control-panel/control-panel-laravel](https://github.com/liberu-control-panel/control-panel-laravel) | Hosting, infrastructure, DNS, mail, databases, backups, and security operations |
| **Automation** | [liberu-automation/automation-laravel](https://github.com/liberu-automation/automation-laravel) | Governed workflows, provider-neutral AI, approvals, and connectors |

## Security

Please do not report security vulnerabilities through public GitHub issues.
Follow our [Security Policy](https://github.com/liberusoftware/documentation/blob/main/architecture/SECURITY.md) for private reporting and supported versions.

## License

This project is open-source software. You may use, modify, and distribute it
under the terms described in [LICENSE.md](LICENSE.md).

The linked license text is authoritative; this summary is not legal advice.

## Feedback and contributing

Feedback and contributions are welcome. You can help by reporting reproducible
bugs, proposing focused enhancements, improving documentation or translations,
and submitting tested code changes.

Before contributing, please read [CONTRIBUTING.md](https://github.com/liberusoftware/documentation/blob/main/standards/CONTRIBUTING.md) and our
[Code of Conduct](https://github.com/liberusoftware/documentation/blob/main/architecture/CODE_OF_CONDUCT.md). Search existing issues first, then use
the appropriate issue template. Pull requests should explain the problem and
approach, remain focused, include or update tests, pass the required workflows,
and document user-visible or breaking changes.

## Contributors

Thank you to everyone who helps improve Liberu.

<a href="https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=liberusoftware/module-ecommerce-multi-tender-payments-filament" alt="Contributors to liberusoftware/module-ecommerce-multi-tender-payments-filament">
</a>

[View the full contributors graph](https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament/graphs/contributors).
