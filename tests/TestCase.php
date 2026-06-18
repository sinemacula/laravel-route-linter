<?php

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SineMacula\RouteLinter\RouteLinterServiceProvider;

/**
 * Base test case for the route-linter package.
 *
 * Registers the package service provider against a Testbench application. The
 * linter touches neither the database nor the cache, so no further environment
 * setup is required; individual tests seed route-linter config as needed.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get the package providers.
     *
     * @param  mixed  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [
            RouteLinterServiceProvider::class,
        ];
    }
}
