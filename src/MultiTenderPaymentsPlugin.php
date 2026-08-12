<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\MultiTenderPayments\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * What a panel attaches to get the multi-tender operator surface.
 *
 * Nothing in this package registers globally. A host chooses which panel sees
 * payment plans by attaching this plugin to that panel and no other, which is
 * the difference between a package that contributes UI and a package that
 * imposes it.
 *
 * The id is fixed and matches `extra.liberu.name` and `module.json`'s `name`.
 * It is how a panel looks this plugin up again — `filament('…')` — so it is
 * part of the published surface and does not change.
 *
 * Discovery is pointed at this package's own resource namespace and nothing
 * else. Handing Filament a broader root would make the host's resources this
 * package's responsibility.
 */
final class MultiTenderPaymentsPlugin implements Plugin
{
    public const ID = 'ecommerce-multi-tender-payments-filament';

    public static function make(): self
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function register(Panel $panel): void
    {
        $panel->discoverResources(
            in: __DIR__.'/Resources',
            for: __NAMESPACE__.'\\Resources',
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
