<?php

declare(strict_types = 1);

namespace Tests\Integration\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\AbstractRouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Console\LintRoutesCommand;
use SineMacula\RouteLinter\Console\UncachedLintProcess;
use Tests\TestCase;

/**
 * Integration tests for the LintRoutesCommand Artisan command.
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

    /** @var string The fixture application linted uncached and cached. */
    private const string FIXTURE_APPLICATION = __DIR__ . '/../../Fixtures/Application';

    /** @var string A child process that writes to both streams and exits with code 3. */
    private const string FAILING_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Failing';

    /** @var string A child process that records the command line it received. */
    private const string ECHO_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Echo';

    /** @var array<int, string> Verb denylist used across tests. */
    private const array VERB_DENYLIST = [
        'get', 'list', 'create', 'add', 'update', 'edit', 'delete',
        'remove', 'cancel', 'login', 'logout', 'search', 'fetch',
    ];

    /**
     * Provide routes with the exit code linting them gives.
     *
     * @return iterable<string, array{string, string, list<string>|null, int}>
     */
    public static function provideExitCodes(): iterable
    {
        yield 'error' => ['getUsers', 'get-users', null, 1];
        yield 'clean' => ['users', 'users.index', null, 0];
        yield 'warning only' => ['users', 'users.getAll', null, 0];
        yield 'error suppressed inline' => ['getUsers', 'get-users', [], 0];
    }

    /**
     * Test that the command exits non-zero only for unsuppressed errors.
     *
     * @param  string  $uri
     * @param  string  $name
     * @param  list<string>|null  $suppressedRules
     * @param  int  $exit
     * @return void
     */
    #[DataProvider('provideExitCodes')]
    public function testExitCodeReflectsErrorFindings(string $uri, string $name, ?array $suppressedRules, int $exit): void
    {
        $this->seedConfig();

        $route = $this->getRouter()->get($uri, fn () => [])->name($name);

        if ($suppressedRules !== null) {
            $route->ignoreRouteLint($suppressedRules, 'All rules suppressed for this legacy route.'); // @phpstan-ignore method.notFound
        }

        $this->runCommand()->assertExitCode($exit);
    }

    /**
     * Test that the command prints error findings before failing.
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
     * Test that a waiver without a reason fails the command.
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
     * Test that an unused inline suppression is reported without failing.
     *
     * @return void
     */
    public function testUnusedInlineSuppressionIsReportedWithoutFailing(): void
    {
        $this->seedConfig();

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
     * Test that caching the route table does not change the verdict.
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
     * Test that a cached run passes the child's output and exit code through.
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
     * Test that a cached run passes the selected environment to the child.
     *
     * @return void
     */
    public function testCachedRunCarriesTheSelectedEnvironmentOver(): void
    {
        $child = $this->lintEchoChild(['--env' => 'staging'], []);

        self::assertContains('--env=staging', $child['arguments']);
    }

    /**
     * Test that a cached run withholds an environment name with its own file.
     *
     * @return void
     */
    public function testCachedRunWithholdsAnEnvironmentNameThatWouldSelectAnotherFile(): void
    {
        $child = $this->lintEchoChild([], ['APP_ENV' => 'probe']);

        self::assertFalse($child['appEnv']);
    }

    /**
     * Test that a cached run with cached configuration passes the environment
     * name on.
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
     * Test that a child which still finds the routes cached fails.
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
     * Run a cached lint against the echo child and decode what it received.
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
     * Run the command and capture its exit code and output.
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
     * Cache the route table in place, as `route:cache` does.
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
     * Run the command.
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
     * Get the router bound to the booted application.
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
