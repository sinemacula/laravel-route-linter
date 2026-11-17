<?php

declare(strict_types = 1);

namespace Tests\Integration\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\AbstractRouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Console\LintRoutesCommand;
use SineMacula\RouteLinter\Console\UncachedLintProcess;
use Tests\TestCase;

/**
 * Integration tests for the LintRoutesCommand Artisan command.
 *
 * Registers fixture routes on the booted framework router and drives the
 * command via Testbench's artisan() helper, asserting exit codes and output.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(LintRoutesCommand::class)]
final class LintRoutesCommandTest extends TestCase
{
    /** @var string The command signature under test. */
    private const string COMMAND = 'route:lint';

    /** @var string The fixture application, whose routes are linted uncached and cached. */
    private const string FIXTURE_APPLICATION = __DIR__ . '/../../Fixtures/Application';

    /** @var string A child process that writes to both streams and exits with code 3. */
    private const string FAILING_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Failing';

    /** @var string A child process that records the command line it received. */
    private const string ECHO_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Echo';

    /** @var array<int, string> Default verb denylist used across tests. */
    private const array VERB_DENYLIST = [
        'get', 'list', 'create', 'add', 'update', 'edit', 'delete',
        'remove', 'cancel', 'login', 'logout', 'search', 'fetch',
    ];

    /**
     * Test that the command exits non-zero when an error-severity violation is
     * present.
     *
     * @return void
     */
    public function testExitsNonZeroWhenErrorViolationPresent(): void
    {
        $this->seedConfig();

        $this->getRouter()->get('getUsers', fn () => [])->name('get-users');

        $this->runCommand()->assertExitCode(1);
    }

    /**
     * Test that the command exits zero when the route table is clean.
     *
     * @return void
     */
    public function testExitsZeroWhenTableIsClean(): void
    {
        $this->seedConfig();

        $this->getRouter()->get('users', fn () => [])->name('users.index');

        $this->runCommand()->assertExitCode(0);
    }

    /**
     * Test that the command prints error findings in the output before failing.
     *
     * @return void
     */
    public function testPrintsFindingsBeforeFailing(): void
    {
        $this->seedConfig();

        $this->getRouter()->get('getUsers', fn () => [])->name('get-users');

        $this->runCommand()
            ->expectsOutputToContain('Route linting errors')
            ->assertExitCode(1);
    }

    /**
     * Test that a misconfigured waiver (entry without a reason) exits non-zero
     * with an error message.
     *
     * @return void
     */
    public function testMisconfiguredWaiverFailsTheCommand(): void
    {
        $this->seedConfig([
            ['match' => 'legacy.route'],
        ]);

        $this->getRouter()->get('users', fn () => [])->name('users.index');

        $this->runCommand()
            ->expectsOutputToContain('missing a required reason')
            ->assertExitCode(1);
    }

    /**
     * Test that a route table with only warning-severity findings exits zero.
     *
     * @return void
     */
    public function testWarningOnlyTableExitsZero(): void
    {
        $this->seedConfig();

        // A named route whose name does not follow {resource}.{action} -
        // triggers R8 (warning)
        $this->getRouter()->get('users', fn () => [])->name('users.getAll');

        $this->runCommand()->assertExitCode(0);
    }

    /**
     * Test that a route whose only error is inline-suppressed exits zero, and
     * that the unused suppression (the route is otherwise clean) is surfaced in
     * the output but does not change the exit code.
     *
     * @return void
     */
    public function testInlineSuppressedRouteExitsZeroAndSurfacesStaleSuppression(): void
    {
        $this->seedConfig();

        // /users is a clean route; the inline suppression for R1 fires on
        // nothing
        $this->getRouter()->get('users', fn () => [])
            ->name('users.index')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint(['R1'], 'Unnecessary waiver added for demonstration.');

        $this->runCommand()
            ->expectsOutputToContain('Stale waivers / unused suppressions')
            ->expectsOutputToContain('suppressed nothing')
            ->assertExitCode(0);
    }

    /**
     * Test that a route whose only error is fully suppressed by an inline
     * suppression (all rules) exits zero.
     *
     * @return void
     */
    public function testFullyInlineSuppressedRouteExitsZero(): void
    {
        $this->seedConfig();

        // /getUsers triggers R1; inline suppression covers all rules
        $this->getRouter()->get('getUsers', fn () => [])
            ->name('get-users')
            // @phpstan-ignore method.notFound
            ->ignoreRouteLint([], 'All rules suppressed for this legacy route.');

        $this->runCommand()->assertExitCode(0);
    }

    /**
     * Test that caching the route table does not change the verdict.
     *
     * The fixture routes are linted from the live router, then the table is
     * cached as `route:cache` does - closures serialised, unnamed routes given
     * generated names - alongside the fixture's cache file, and linted again.
     * Both runs must print the same findings and exit with the same code.
     *
     * @return void
     */
    public function testVerdictIsIndependentOfRouteCacheState(): void
    {
        assert($this->app !== null);

        $this->getRouter()->group(['middleware' => 'web'], self::FIXTURE_APPLICATION . '/routes/web.php');
        $this->app->setBasePath(self::FIXTURE_APPLICATION);
        $this->app->instance('routes.cached', false);

        $uncached = $this->lint();

        $this->cacheRoutes();

        self::assertSame($uncached, $this->lint());
        self::assertSame(1, $uncached['exit']);
        self::assertStringContainsString('[R4] GET,HEAD user/{user} user.show (user)', $uncached['output']);
    }

    /**
     * Test that the child's output and exit code are passed through unchanged.
     *
     * @return void
     */
    public function testCachedRunPassesTheChildVerdictThrough(): void
    {
        assert($this->app !== null);

        $this->app->setBasePath(self::FAILING_CHILD);
        $this->app->instance('routes.cached', true);

        $result = $this->lint();

        self::assertSame(3, $result['exit']);
        self::assertStringContainsString('Linted on stdout.', $result['output']);
        self::assertStringContainsString('Failed on stderr.', $result['output']);
    }

    /**
     * Test that an environment selected for the command is selected for the
     * child too.
     *
     * @return void
     */
    public function testCachedRunCarriesTheSelectedEnvironmentOver(): void
    {
        $child = $this->lintEchoChild(['--env' => 'staging'], []);

        self::assertContains('--env=staging', $child['arguments']);
    }

    /**
     * Test that the child is told which environment file the command loaded, so
     * an environment name with its own file beside it is withheld.
     *
     * @return void
     */
    public function testCachedRunWithholdsAnEnvironmentNameThatWouldSelectAnotherFile(): void
    {
        $child = $this->lintEchoChild([], ['APP_ENV' => 'probe']);

        self::assertFalse($child['appEnv']);
    }

    /**
     * Test that a command with cached configuration, which loaded no
     * environment file, passes its environment name on.
     *
     * @return void
     */
    public function testCachedRunWithCachedConfigurationPassesTheEnvironmentNameOn(): void
    {
        assert($this->app !== null);

        $this->app->instance('config_loaded_from_cache', true);

        $child = $this->lintEchoChild([], ['APP_ENV' => 'probe']);

        self::assertSame('probe', $child['appEnv']);
    }

    /**
     * Test that a child which still finds the routes cached fails rather than
     * spawning another child.
     *
     * @return void
     */
    public function testChildThatCannotBypassTheRouteCacheFails(): void
    {
        assert($this->app !== null);

        $this->app->setBasePath(self::FAILING_CHILD);
        $this->app->instance('routes.cached', true);

        putenv(UncachedLintProcess::MARKER . '=1');

        try {
            $this->runCommand()
                ->expectsOutputToContain('The route cache could not be bypassed')
                ->assertExitCode(1);
        } finally {
            putenv(UncachedLintProcess::MARKER);
        }
    }

    /**
     * Run the command against the echo child with routes cached and the fixture
     * environment file loaded, and decode what the child received.
     *
     * @param  array<string, string>  $options
     * @param  array<string, string>  $environment
     * @return array{arguments: list<string>, appEnv: false|string}
     */
    private function lintEchoChild(array $options, array $environment): array
    {
        assert($this->app !== null);

        $this->app->setBasePath(self::ECHO_CHILD);
        $this->app->loadEnvironmentFrom('environment');
        $this->app->instance('routes.cached', true);

        $file = tempnam(sys_get_temp_dir(), 'route-linter-echo');

        assert($file !== false);

        $environment['ROUTE_LINTER_ECHO_FILE'] = $file;

        foreach ($environment as $name => $value) {
            putenv($name . '=' . $value);
        }

        try {
            $this->app->make(Kernel::class)->call(self::COMMAND, $options);

            /** @var array{arguments: list<string>, appEnv: false|string} */
            return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            foreach (array_keys($environment) as $name) {
                putenv($name);
            }

            unlink($file);
        }
    }

    /**
     * Run the lint-routes command and capture its exit code and output.
     *
     * @return array{exit: int, output: string}
     */
    private function lint(): array
    {
        assert($this->app !== null);

        $kernel = $this->app->make(Kernel::class);

        return [
            'exit'   => $kernel->call(self::COMMAND),
            'output' => $kernel->output(),
        ];
    }

    /**
     * Cache the route table in place, as `route:cache` does, and let the cached
     * state be detected from the application's route cache file.
     *
     * @return void
     */
    private function cacheRoutes(): void
    {
        assert($this->app !== null);

        $router = $this->getRouter();
        $routes = $router->getRoutes();

        assert($routes instanceof AbstractRouteCollection);

        foreach ($routes->getRoutes() as $route) {
            $route->prepareForSerialization();
        }

        $router->setCompiledRoutes($routes->compile());
        $this->app->forgetInstance('routes.cached');
    }

    /**
     * Run the lint-routes command.
     *
     * @return \Illuminate\Testing\PendingCommand
     */
    private function runCommand(): PendingCommand
    {
        $command = $this->artisan(self::COMMAND);

        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * Seed the route-linter config section.
     *
     * @param  array<int, array<string, string>>  $exemptions
     * @return void
     */
    private function seedConfig(array $exemptions = []): void
    {
        config()->set('route-linter.verb_denylist', self::VERB_DENYLIST);
        config()->set('route-linter.remediation_hints', []);
        config()->set('route-linter.exemptions', $exemptions);
        config()->set('route-linter.uncountables', []);
    }

    /**
     * Get the router instance bound to the booted application.
     *
     * @return \Illuminate\Routing\Router
     */
    private function getRouter(): Router
    {
        assert($this->app !== null);

        /** @var \Illuminate\Routing\Router */
        return $this->app->make('router');
    }
}
