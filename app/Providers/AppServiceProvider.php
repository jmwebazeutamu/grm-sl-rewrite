<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Strict mode catches lazy-loading + missing-attribute issues in dev
        // but is noisy for tests (factory-created models don't always load
        // every nullable column). Keep it on only for local dev.
        Model::shouldBeStrict($this->app->environment('local'));
        Model::unguard(false);

        // Map every App\Domain\*\Models\Foo to Database\Factories\FooFactory.
        // Laravel's default resolver uses the full namespace after `App\`,
        // which doesn't fit our domain-partitioned layout.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
