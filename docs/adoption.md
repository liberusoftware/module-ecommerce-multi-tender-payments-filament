# Adopting this module

## 1. Repositories

Neither this package nor the domain package it presents is on Packagist, and
Composer honours `repositories` **only from the root manifest**. This package
carries a VCS entry for the domain package so that its own CI can resolve it,
but that does nothing for you — your application is root, so your
`composer.json` needs both entries:

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-multi-tender-payments" },
  { "type": "vcs", "url": "https://github.com/liberusoftware/module-ecommerce-multi-tender-payments-filament" }
]
```

```bash
composer require liberusoftware/ecommerce-multi-tender-payments-filament
```

## 2. Enablement

Installing boots nothing. This package declares no `extra.laravel.providers`, so
Composer putting it in `vendor/` registers no provider and contributes no UI.

The host's `ModuleManagerServiceProvider` globs `config('modules.paths')` for
`*/module.json` and registers only the modules named in `MODULES_ENABLED`:

```dotenv
MODULES_ENABLED=ecommerce-multi-tender-payments,ecommerce-multi-tender-payments-filament
```

Both. The presentation module presents the domain module; without the domain
module enabled there are no migrations and no models to present.

## 3. Attach the plugin to a panel

Nothing registers globally. A panel sees payment plans because you attached the
plugin to that panel and no other:

```php
use Liberu\Ecommerce\MultiTenderPayments\Filament\MultiTenderPaymentsPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('admin')
        ->plugin(MultiTenderPaymentsPlugin::make());
}
```

**Do not attach it to a panel with tenancy enabled.** The domain package holds
no tenant or site column — a plan keys on an opaque `order_reference` and
nothing else — so there is nothing for Filament's tenant scope to bind to.
Attaching it anyway raises `PanelContextUnavailable` the moment the resource
builds a query, which is the correct failure direction: an error, not one
tenant reading another's plans. Partition at the resolver seam instead, or take
the `0.2.0` column the domain package's README describes.

## 4. Bind the domain's two resolvers

The domain publishes `ResolvesPayableTotal` and `ResolvesTenderCapacity` and
implements neither, with no default binding. Until the host binds them, this
surface can list plans and will show every outstanding balance as `Unknown` —
which is deliberate. A default binding would mean a half-configured deployment
quietly treating an order total as zero, and a zero balance reads as *settled*.

```php
$this->app->bind(ResolvesPayableTotal::class, OrderPayableTotal::class);
$this->app->bind(ResolvesTenderCapacity::class, TenderCapacityRouter::class);
```

`ResolvesTenderCapacity` is not used by this package — it is consulted when a
plan is admitted, which happens elsewhere — but a deployment wanting the whole
module working needs both.

## 5. Grant the three abilities

Everything denies until you say otherwise. Define the abilities against whatever
your application already uses for permissions:

```php
Gate::define('ecommerce-multi-tender-payments.view', fn (User $user): bool => $user->can('finance.read'));
Gate::define('ecommerce-multi-tender-payments.reverse', fn (User $user): bool => $user->can('finance.write'));
Gate::define('ecommerce-multi-tender-payments.view-gateway-reference', fn (User $user): bool => $user->can('finance.gateway'));
```

Granting `view` alone gives a reconciliation queue that reads and nothing more.
That is the sensible default for most operators.

### What you cannot grant

Everything else is structural, not discretionary, and no grant reopens it:
creating a tender, editing one, deleting one, or re-parenting one through
`associate`, `attach`, `detach` or `dissociate`. The policies answer `false` and
the pages and actions that would use those answers do not exist.

One caveat worth knowing: a `Gate::before` callback that returns `true` for an
administrator short-circuits every policy method in Laravel, including these.
That is why the absence of the pages and actions is the primary guarantee and
the policies are the second one.

## 6. Migrating off the host's columns

This package adds no table and no column. Everything in that part of the move —
`orders.payment_method`, `orders.transaction_id`, the two `payment_status`
columns, `orders.total_amount`, and the `payment_methods` table — is covered by
`docs/adoption.md` in the domain package, which is where the data lives.

## 7. Overriding the surface

Filament resources are static classes; a host that wants a different table
extends `PaymentPlanResource` and registers its own subclass instead of relying
on discovery. Do not remove the policies while doing so: an unanswered ability
is an allowed one.
