<?php

namespace SineMacula\RouteLinter;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use SineMacula\RouteLinter\Configuration\ConfigRuleConfiguration;
use SineMacula\RouteLinter\Console\LintRoutesCommand;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Contracts\LintReporter;
use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Contracts\Rule;
use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;
use SineMacula\RouteLinter\Output\ConsoleLintReporter;
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use SineMacula\RouteLinter\Sources\RouterRouteSource;
use SineMacula\RouteLinter\Support\RouteLintMacros;

/**
 * Registers the route-linter command, config, port bindings, and route macro.
 *
 * Binds the four ports to their default adapters and assembles the
 * RouteLintEngine from the configured rule list, resolving each rule through
 * the container so rules may declare constructor dependencies. The LintRoutes
 * use case is auto-resolved through constructor injection from these bindings.
 * All configuration is read through the RuleConfiguration port, so the adapter
 * is the single place that validates the `route-linter.*` config surface.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final class RouteLinterServiceProvider extends ServiceProvider
{
    /**
     * Register the package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/route-linter.php', 'route-linter');

        $this->app->singleton(RouteSource::class, fn ($app) => new RouterRouteSource($app['router']));

        $this->app->singleton(RuleConfiguration::class, ConfigRuleConfiguration::class);

        $this->app->singleton(Inflector::class, function (): FrameworkInflector {

            $uncountables = Config::get('route-linter.uncountables');

            return new FrameworkInflector(is_array($uncountables) ? $uncountables : []);
        });

        $this->app->bind(VerbDenylist::class, function (): VerbDenylist {

            $denylist = Config::get('route-linter.verb_denylist');
            $hints    = Config::get('route-linter.remediation_hints');

            return new VerbDenylist(
                is_array($denylist) ? $denylist : [],
                is_array($hints) ? $hints : [],
            );
        });

        $this->app->bind(LintReporter::class, fn ($app, array $parameters): ConsoleLintReporter => new ConsoleLintReporter($parameters['output']));

        $this->app->singleton(RouteLintEngine::class, fn ($app): RouteLintEngine => new RouteLintEngine(...$this->resolveRules($app)));
    }

    /**
     * Bootstrap the package services.
     *
     * @return void
     */
    public function boot(): void
    {
        RouteLintMacros::register();

        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../config/route-linter.php' => $this->app->configPath('route-linter.php'),
            ], 'route-linter-config');

            $this->commands([LintRoutesCommand::class]);
        }
    }

    /**
     * Resolve the configured rule classes into rule instances in declared
     * order.
     *
     * Each entry in `route-linter.rules` must be a class string implementing
     * the Rule contract; rules are resolved through the container so they may
     * declare constructor dependencies. A non-array config value yields an
     * empty rule set.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array<int, \SineMacula\RouteLinter\Contracts\Rule>
     *
     * @throws \SineMacula\RouteLinter\Exceptions\InvalidConfigurationException
     */
    private function resolveRules(Application $app): array
    {
        $classes = Config::get('route-linter.rules');

        if (!is_array($classes)) {
            return [];
        }

        return array_map(
            function (mixed $class) use ($app): Rule {

                if (!is_string($class) || !is_subclass_of($class, Rule::class)) {
                    $got = is_string($class) ? $class : get_debug_type($class);

                    throw new InvalidConfigurationException(sprintf('Configured rule must implement %s; got "%s".', Rule::class, $got));
                }

                return $app->make($class);
            },
            array_values($classes),
        );
    }
}
