<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Policies\PaymentPlanPolicy;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Policies\TenderEntryPolicy;
use Liberu\Ecommerce\MultiTenderPayments\Models\PaymentPlan;
use Liberu\Ecommerce\MultiTenderPayments\Models\TenderEntry;

/**
 * The module's only registration, and it registers two policies.
 *
 * The policies are global rather than per-panel on purpose: a model with no
 * policy is exposed rather than safe, and that is true wherever the model is
 * reached from. Binding them here means the domain's two models are answered
 * for even in an application that never attaches
 * {@see MultiTenderPaymentsPlugin} to a panel at all.
 *
 * Everything a panel actually shows comes from that plugin, which a host
 * attaches to the panels it chooses. There is no `extra.laravel.providers`, so
 * Composer installing this package boots nothing; enablement is the host's
 * explicit decision through `MODULES_ENABLED`.
 */
final class MultiTenderPaymentsFilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(PaymentPlan::class, PaymentPlanPolicy::class);
        Gate::policy(TenderEntry::class, TenderEntryPolicy::class);
    }
}
