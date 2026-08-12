<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\Pages;

use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Resources\PaymentPlans\PaymentPlanResource;

/**
 * The queue.
 *
 * No header actions. The absence of a create action is the point, not an
 * omission: a plan is opened by the domain when an order resolves a payable
 * total, and there is nothing here for an operator to open one with.
 */
class ListPaymentPlans extends ListRecords
{
    protected static string $resource = PaymentPlanResource::class;
}
