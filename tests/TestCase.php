<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Foundation\Application;
use Liberu\Ecommerce\MultiTenderPayments\Filament\Tests\Fixtures\OperatorPanelProvider;
use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\TestUser;
use Liberu\PackageTestbench\UsesTestUser;
use Livewire\LivewireServiceProvider;

abstract class TestCase extends PackageTestCase
{
    /** Filament renders for an actor, so the suite needs a users table and a user to be. */
    use UsesTestUser;

    /**
     * Testbench runs no package discovery, and the testbench base case can only
     * see the `extra.laravel.providers` of *direct* dependencies. Filament's
     * panel provider boots against bindings its sibling packages register, so
     * the ones this package leans on are named here rather than hoped for.
     * Order is irrelevant — every provider's `register()` runs before any
     * provider's `boot()`.
     *
     * The panel provider comes last because it is the fixture: a host attaching
     * this package's plugin to one panel.
     *
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_unique([
            ...parent::getPackageProviders($app),
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            OperatorPanelProvider::class,
        ]));
    }

    /**
     * Calling the parent is not optional: the application key it sets is what
     * everything rendering a view depends on.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }
}
