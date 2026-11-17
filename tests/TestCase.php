<?php

declare(strict_types = 1);

namespace Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SineMacula\RouteLinter\RouteLinterServiceProvider;

/**
 * Base test case that registers the package against a Testbench application.
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
    #[\Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            RouteLinterServiceProvider::class,
        ];
    }
}
