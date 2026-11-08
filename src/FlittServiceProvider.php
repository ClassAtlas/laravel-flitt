<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use Illuminate\Contracts\Foundation\Application;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FlittServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('flitt')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->bindIf(CredentialsResolver::class, ConfigCredentialsResolver::class);

        $this->app->scoped(Flitt::class, static fn (Application $app): Flitt => new Flitt(
            $app->make('config'),
            $app->make('log'),
        ));
    }
}
