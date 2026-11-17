<?php

declare(strict_types = 1);

namespace Tests\Integration;

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use SineMacula\RouteLinter\Sources\RouterRouteSource;
use SineMacula\RouteLinter\Violation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Fixtures\Rules\ParameterEchoRule;
use Tests\TestCase;

/**
 * Integration tests for the package service provider.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteLinterServiceProvider::class)]
final class RouteLinterServiceProviderTest extends TestCase
{
    /**
     * Provide each port with its default adapter.
     *
     * @return iterable<string, array{class-string, class-string}>
     */
    public static function providePortAdapters(): iterable
    {
        yield 'route source' => [RouteSource::class, RouterRouteSource::class];
        yield 'rule configuration' => [RuleConfiguration::class, ConfigRuleConfiguration::class];
        yield 'inflector' => [Inflector::class, FrameworkInflector::class];
    }

    /**
     * Test that each port resolves to its default adapter.
     *
     * @param  class-string  $port
     * @param  class-string  $adapter
     * @return void
     */
    #[DataProvider('providePortAdapters')]
    public function testResolvesPortToDefaultAdapter(string $port, string $adapter): void
    {
        assert($this->app !== null);

        self::assertInstanceOf($adapter, $this->app->make($port));
    }

    /**
     * Test that the rule engine resolves as a singleton.
     *
     * @return void
     */
    public function testResolvesRuleEngineAsConfiguredSingleton(): void
    {
        assert($this->app !== null);

        $first  = $this->app->make(RouteLintEngine::class);
        $second = $this->app->make(RouteLintEngine::class);

        self::assertInstanceOf(RouteLintEngine::class, $first);
        self::assertSame($first, $second, 'The engine must be bound as a singleton.');
    }

    /**
     * Test that the package config is merged under the `route-linter` key.
     *
     * @return void
     */
    public function testMergesPackageConfiguration(): void
    {
        self::assertIsArray(config('route-linter.verb_denylist'));
        self::assertIsArray(config('route-linter.uncountables'));
    }

    /**
     * Test that the resolved inflector is primed with the shipped uncountables.
     *
     * @return void
     */
    public function testResolvedInflectorHonoursConfiguredUncountables(): void
    {
        assert($this->app !== null);

        $inflector = $this->app->make(Inflector::class);

        self::assertSame('media', $inflector->singular('media'));
    }

    /**
     * Test that the resolved engine uses the shipped verb denylist and hints.
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

        self::assertNotEmpty($violations, 'R1 must fire using the shipped verb denylist.');
        self::assertSame('get', $violations[0]->offendingSurface);
        self::assertNotNull($violations[0]->remediationHint, 'R1 must carry the shipped remediation hint.');
    }

    /**
     * Test that the verb allowlist clears a homograph from the shipped denylist
     * while the rest of the list still applies.
     *
     * @return void
     */
    public function testVerbAllowlistClearsAHomographFromTheShippedDenylist(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.verb_allowlist', ['reset']);

        $engine = $this->app->make(RouteLintEngine::class);
        $config = $this->app->make(RuleConfiguration::class)->load();

        $verbs = static fn (string $uri, string $name): array => array_map(
            static fn (Violation $violation): string => $violation->offendingSurface,
            array_values(array_filter(
                $engine->inspect(new NormalisedRoute($uri, ['POST'], $name, explode('/', $uri), []), $config),
                static fn (Violation $violation): bool => $violation->ruleId === 'R1',
            )),
        );

        self::assertSame([], $verbs('password-resets', 'password-resets.store'));
        self::assertSame(['cancel'], $verbs('orders/{order}/cancel', 'orders.cancel'));
    }

    /**
     * Test that the package config is publishable under its tag.
     *
     * @return void
     */
    public function testPublishesPackageConfigUnderTag(): void
    {
        $paths = RouteLinterServiceProvider::pathsToPublish(RouteLinterServiceProvider::class, 'route-linter-config');

        self::assertNotEmpty($paths, 'The package config must be publishable under the route-linter-config tag.');

        $source = array_key_first($paths);

        self::assertIsString($source);
        self::assertStringEndsWith('config/route-linter.php', $source);
        self::assertFileExists($source);
    }

    /**
     * Test that boot registers the route macro and the Artisan command.
     *
     * @return void
     */
    public function testBootRegistersMacroAndCommand(): void
    {
        self::assertTrue(Route::hasMacro('ignoreRouteLint'), 'The ignoreRouteLint macro must be registered.');

        $commands = array_map(static fn (object $command): string => $command::class, array_values(Artisan::all()));

        self::assertContains(LintRoutesCommand::class, $commands, 'The route:lint command must be registered.');
    }

    /**
     * Test that the reporter port resolves to the console reporter.
     *
     * @return void
     */
    public function testResolvesLintReporterPortToConsoleReporter(): void
    {
        assert($this->app !== null);

        $output = new OutputStyle(new ArrayInput([]), new BufferedOutput);

        self::assertInstanceOf(ConsoleLintReporter::class, $this->app->make(LintReporter::class, ['output' => $output]));
    }

    /**
     * Test that a custom rule listed in config is resolved and run.
     *
     * @return void
     */
    public function testRegistersAndRunsCustomRuleFromConfig(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.rules', [ParameterEchoRule::class]);
        config()->set('route-linter.exemptions', []);

        $this->app->make(Router::class)->get('teams/{team}/members/{member}', fn () => [])->name('teams.members.index');

        $report = $this->app->make(LintRoutes::class)->lint();

        $surfaces = array_map(
            static fn (Violation $violation): string => $violation->offendingSurface,
            $report->warnings(),
        );

        self::assertContains('team,member', $surfaces, 'A custom rule from config must run and observe the brace-stripped parameter names.');
    }

    /**
     * Test that a configured class that is not a rule is rejected.
     *
     * @return void
     */
    public function testThrowsWhenConfiguredRuleClassIsNotARule(): void
    {
        config()->set('route-linter.rules', [\stdClass::class]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\stdClass::class);

        assert($this->app !== null);

        $this->app->make(RouteLintEngine::class);
    }

    /**
     * Test that a non-array rules config yields an engine with no rules.
     *
     * @return void
     */
    public function testNonArrayRulesConfigYieldsRuleLessEngine(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.rules', 'not-an-array');

        self::assertInstanceOf(RouteLintEngine::class, $this->app->make(RouteLintEngine::class));
    }

    /**
     * Test that the resolved verb denylist skips malformed config entries.
     *
     * @return void
     */
    public function testResolvedVerbDenylistSkipsMalformedConfigEntries(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.verb_denylist', ['get', ['fetch'], 7, 'create']);
        config()->set('route-linter.remediation_hints', [
            'fetch' => ['Not a hint.'],
            'A hint without a verb.',
            'get'    => 'Use a noun resource instead.',
            'create' => 'POST to the collection instead.',
        ]);

        $denylist = $this->app->make(VerbDenylist::class);

        self::assertTrue($denylist->contains('get'));
        self::assertTrue($denylist->contains('create'));
        self::assertFalse($denylist->contains('7'));
        self::assertSame('Use a noun resource instead.', $denylist->hint('get'));
        self::assertSame('POST to the collection instead.', $denylist->hint('create'));
        self::assertNull($denylist->hint('fetch'));
        self::assertNull($denylist->hint('0'));
    }

    /**
     * Test that non-array word lists leave the denylist and inflector empty.
     *
     * @return void
     */
    public function testNonArrayWordConfigYieldsEmptyDenylistAndInflector(): void
    {
        assert($this->app !== null);

        config()->set('route-linter.verb_denylist', 'get');
        config()->set('route-linter.remediation_hints', 'Use a noun resource instead.');
        config()->set('route-linter.uncountables', 'media');

        $denylist = $this->app->make(VerbDenylist::class);

        self::assertFalse($denylist->contains('get'));
        self::assertNull($denylist->hint('get'));
        self::assertSame('medium', $this->app->make(Inflector::class)->singular('media'));
    }

    /**
     * Test that register and boot can run again on a booted application.
     *
     * @return void
     */
    public function testProviderLifecycleIsRepeatable(): void
    {
        assert($this->app !== null);

        $provider = new RouteLinterServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        self::assertInstanceOf(RouterRouteSource::class, $this->app->make(RouteSource::class));
        self::assertTrue(Route::hasMacro('ignoreRouteLint'));
    }

    /**
     * Test that boot skips the console-only registrations outside the console.
     *
     * @return void
     */
    public function testBootSkipsConsoleRegistrationsOutsideConsole(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('runningInConsole')->willReturn(false);
        $app->expects(self::never())->method('configPath');

        (new RouteLinterServiceProvider($app))->boot();

        self::assertTrue(Route::hasMacro('ignoreRouteLint'), 'The macro must still be registered outside the console.');
    }
}
