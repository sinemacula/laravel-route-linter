<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Support;

use Illuminate\Routing\Route;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;

/**
 * Registers route macros used by the route-lint suppression feature.
 *
 * The static `register()` entry point is idempotent - it checks for an existing
 * macro before binding - so it is safe to call from service-provider `boot()`
 * methods without risk of double-registration across test resets or multiple
 * provider loads.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLintMacros
{
    /**
     * Register all route-lint macros onto the Illuminate Route class.
     *
     * Idempotent: skips registration when the macro already exists.
     *
     * @return void
     */
    public static function register(): void
    {
        if (Route::hasMacro('ignoreRouteLint')) {
            return;
        }

        /*
         * Suppress one or more route-lint rules for this route.
         *
         * Pass an empty `$ruleIds` array to suppress all rules. Multiple calls
         * accumulate; each call appends an independent suppression entry to the
         * route action. Data is stored as plain arrays so it survives
         * `route:cache` serialisation.
         *
         * @param  list<string>  $ruleIds  Rule IDs to suppress; an empty list
         *   means all rules.
         * @param  string  $reason  Non-empty written justification for this
         *   suppression.
         * @return \Illuminate\Routing\Route
         *
         * @throws InvalidConfigurationException
         */
        Route::macro('ignoreRouteLint', function (array $ruleIds, string $reason): Route {
            // The macro closure is bound to the Illuminate Route instance.
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
