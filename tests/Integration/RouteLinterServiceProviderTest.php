<?php

namespace Tests\Integration;

use Illuminate\Console\OutputStyle;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Configuration\ConfigRuleConfiguration;
use SineMacula\RouteLinter\Console\LintRoutesCommand;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Contracts\LintReporter;
use SineMacula\RouteLinter\Contracts\RouteSource;
use SineMacula\RouteLinter\Contracts\RuleConfiguration;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;
use SineMacula\RouteLinter\LintRoutes;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Output\ConsoleLintReporter;
use SineMacula\RouteLinter\RouteLintEngine;
use SineMacula\RouteLinter\RouteLinterServiceProvider;
use SineMacula\RouteLinter\Sources\RouterRouteSource;
use SineMacula\RouteLinter\Violation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\TestCase;

/**
 * Integration tests for the package service provider.
 *
 * Drives the composition root against the booted Testbench application: every
 * port must resolve to its default adapter, the engine must assemble, the
 * Artisan command must be registered, the route macro must be bound, and the
 * package config must be merged. Resolving each binding here also exercises the
 * lazy singleton factories the provider registers.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteLinterServiceProvider::class)]
class RouteLinterServiceProviderTest extends TestCase
{
    /**
     * Test that the route-source port resolves to the router-backed adapter.
     *
     * @return void
     */
    public function testResolvesRouteSourcePortToRouterAdapter(): void
    {
        assert($this->app !== null);

        static::assertInstanceOf(RouterRouteSource::class, $this->app->make(RouteSource::class));
    }

    /**
     * Test that the rule-configuration port resolves to the config-backed
     * adapter.
     *
     * @return void
     */
    public function testResolvesRuleConfigurationPortToConfigAdapter(): void
    {
        assert($this->app !== null);

        static::assertInstanceOf(ConfigRuleConfiguration::class, $this->app->make(RuleConfiguration::class));
    }

    /**
     * Test that the inflector port resolves to the framework inflector adapter.
     *
     * @return void
     */
    public function testResolvesInflectorPortToFrameworkInflector(): void
    {
        assert($this->app !== null);

        $inflector = $this->app->make(Inflector::class);

        static::assertInstanceOf(Inflector::class, $inflector);
        static::assertInstanceOf(FrameworkInflector::class, $inflector);
    }

    /**
     * Test that the rule engine resolves as a singleton with the full rule set
     * assembled.
     *
     * @return void
     */
    public function testResolvesRuleEngineAsConfiguredSingleton(): void
    {
        assert($this->app !== null);

        $first  = $this->app->make(RouteLintEngine::class);
        $second = $this->app->make(RouteLintEngine::class);

        static::assertInstanceOf(RouteLintEngine::class, $first);
        static::assertSame($first, $second, 'The engine must be bound as a singleton.');
    }

    /**
     * Test that the package config is merged under the `route-linter` key.
     *
     * @return void
     */
    public function testMergesPackageConfiguration(): void
    {
        static::assertIsArray(config('route-linter.verb_denylist'));
        static::assertIsArray(config('route-linter.uncountables'));
    }

    /**
     * Test that the resolved inflector is primed with the shipped uncountables.
     *
     * `media` is a shipped uncountable: the framework inflector would otherwise
     * singularise it to `medium`, so an unchanged result proves the provider
     * passed the configured uncountables into the inflector rather than an
     * empty list.
     *
     * @return void
     */
    public function testResolvedInflectorHonoursConfiguredUncountables(): void
    {
        assert($this->app !== null);

        $inflector = $this->app->make(Inflector::class);

        static::assertSame('media', $inflector->singular('media'));
    }

    /**
     * Test that the resolved engine is primed with the shipped verb denylist
     * and remediation hints.
     *
     * Linting `getUsers` must raise an R1 verb-in-path violation whose surface
     * is the denylisted verb `get` and whose remediation hint is populated —
     * proving the provider fed the configured denylist and hints into the
     * engine rather than empty lists.
     *
     * @return void
     */
    public function testResolvedEngineUsesConfiguredVerbDenylistAndHints(): void
    {
        assert($this->app !== null);

        $engine = $this->app->make(RouteLintEngine::class);
        $config = $this->app->make(RuleConfiguration::class)->load();

        $route = new NormalisedRoute('getUsers', ['GET'], 'get-users', ['getUsers'], []);

        $violations = array_values(array_filter(
            $engine->inspect($route, $config),
            static fn (Violation $violation): bool => $violation->ruleId === 'R1',
        ));

        static::assertNotEmpty($violations, 'R1 must fire using the shipped verb denylist.');
        static::assertSame('get', $violations[0]->offendingSurface);
        static::assertNotNull($violations[0]->remediationHint, 'R1 must carry the shipped remediation hint.');
    }

    /**
     * Test that the package config is registered for publishing under its tag,
     * sourced from the real config file.
     *
     * @return void
     */
    public function testPublishesPackageConfigUnderTag(): void
    {
        $paths = RouteLinterServiceProvider::pathsToPublish(RouteLinterServiceProvider::class, 'route-linter-config');

        static::assertNotEmpty($paths, 'The package config must be publishable under the route-linter-config tag.');

        $source = array_key_first($paths);

        static::assertIsString($source);
        static::assertStringEndsWith('config/route-linter.php', $source);
        static::assertFileExists($source);
    }

    /**
     * Test that boot registers the route macro and the Artisan command.
     *
     * @return void
     */
    public function testBootRegistersMacroAndCommand(): void
    {
        static::assertTrue(Route::hasMacro('ignoreRouteLint'), 'The ignoreRouteLint macro must be registered.');

        $commands = array_map(static fn (object $command): string => $command::class, array_values(Artisan::all()));

        static::assertContains(LintRoutesCommand::class, $commands, 'The route:lint command must be registered.');
    }

    /**
     * Test that the LintReporter port resolves to the console reporter, passing
     * the runtime output through the contextual binding.
     *
     * @return void
     */
    public function testResolvesLintReporterPortToConsoleReporter(): void
    {
        assert($this->app !== null);

        $output = new OutputStyle(new ArrayInput([]), new BufferedOutput);

        static::assertInstanceOf(ConsoleLintReporter::class, $this->app->make(LintReporter::class, ['output' => $output]));
    }

    /**
     * Test that a custom rule listed in `route-linter.rules` is resolved from
     * the container and run by the engine, and that it observes the route's
     * brace-stripped parameter names via NormalisedRoute::$parameters.
     *
     * Proves rule-set extensibility (a consumer adds a rule by class name) and
     * that the parameter-extraction pipeline feeds custom rule authors.
     *
     * @return void
     */
    public function testRegistersAndRunsCustomRuleFromConfig(): void
    {
        assert($this->app !== null);
        $app = $this->app;

        config()->set('route-linter.rules', [ParameterEchoRule::class]);
        config()->set('route-linter.exemptions', []);

        /** @var \Illuminate\Routing\Router $router */
        $router = $app->make(Router::class);
        $router->get('teams/{team}/members/{member}', fn () => [])->name('teams.members.index');

        $report = $app->make(LintRoutes::class)->lint();

        $surfaces = array_map(
            static fn (Violation $violation): string => $violation->offendingSurface,
            $report->warnings(),
        );

        static::assertContains('team,member', $surfaces, 'A custom rule from config must run and observe the brace-stripped parameter names.');
    }

    /**
     * Test that a configured rule class which does not implement Rule is
     * rejected with a clear configuration error rather than a raw type error.
     *
     * @return void
     */
    public function testThrowsWhenConfiguredRuleClassIsNotARule(): void
    {
        assert($this->app !== null);
        $app = $this->app;

        config()->set('route-linter.rules', [\stdClass::class]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\stdClass::class);

        $app->make(RouteLintEngine::class);
    }

    /**
     * Test that a non-array `route-linter.rules` value yields a rule-less
     * engine rather than raising — the lenient resolution mirrors the other
     * surfaces.
     *
     * @return void
     */
    public function testNonArrayRulesConfigYieldsRuleLessEngine(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.rules', 'not-an-array');

        static::assertInstanceOf(RouteLintEngine::class, $this->app->make(RouteLintEngine::class));
    }

    /**
     * Test that register and boot run idempotently when invoked directly.
     *
     * Re-running the provider lifecycle against the booted application must not
     * raise — the singleton bindings simply rebind and the macro registration
     * short-circuits on its idempotency guard.
     *
     * @return void
     */
    public function testProviderLifecycleIsRepeatable(): void
    {
        assert($this->app !== null);

        $provider = new RouteLinterServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        static::assertInstanceOf(RouterRouteSource::class, $this->app->make(RouteSource::class));
        static::assertTrue(Route::hasMacro('ignoreRouteLint'));
    }
}
