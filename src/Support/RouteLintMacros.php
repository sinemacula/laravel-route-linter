<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Support;

use Illuminate\Routing\Route;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Registers the `ignoreRouteLint` route macro.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLintMacros
{
    /**
     * Register the macro unless it is already registered.
     *
     * @return void
     */
    public static function register(): void
    {
        if (Route::hasMacro('ignoreRouteLint')) {
            return;
        }

        // Stored as plain arrays in the route action so it survives route:cache
        Route::macro('ignoreRouteLint', function (array $ruleIds, string $reason): Route {
            if (trim($reason) === '') {
                throw new InvalidConfigurationException('A non-empty reason is required for ignoreRouteLint().');
            }

            $this->action['route-linter::lint-ignore'][] = [
                'rules'  => array_values($ruleIds),
                'reason' => $reason,
            ];

            return $this;
        });
    }
}
