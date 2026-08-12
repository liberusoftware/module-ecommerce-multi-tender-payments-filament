<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages;

use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\PaymentPlanResource;

/**
 * One plan, its outstanding balance, and its ledger beneath.
 *
 * No header actions. There is no edit page to link to and no delete action to
 * offer, because there is nothing on a plan an operator may change.
 */
class ViewPaymentPlan extends ViewRecord
{
    protected static string $resource = PaymentPlanResource::class;
}
