<?php

declare(strict_types = 1);

namespace Tests\Integration\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;
use SineMacula\RouteLinter\Console\UncachedLintProcess;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Tests\Fixtures\Output\BufferedConsoleOutput;

/**
 * Integration tests for the UncachedLintProcess child-process runner.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(UncachedLintProcess::class)]
final class UncachedLintProcessTest extends TestCase
{
    /** @var string A child that records the command line and environment it gets. */
    private const string ECHO_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Echo';

    /** @var string A child that writes to both streams and exits with code 3. */
    private const string FAILING_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Failing';

    /** @var string A child that kills itself with SIGKILL. */
    private const string SIGNALED_CHILD = __DIR__ . '/../../Fixtures/ChildProcesses/Signaled';

    /** @var string The environment file the caller loaded; a `probe` file sits beside it. */
    private const string ENVIRONMENT_FILE = self::ECHO_CHILD . '/environment';

    /**
     * Test that the child runs route:lint with the route cache bypassed.
     *
     * @return void
     */
    public function testRunsRouteLintWithTheRouteCacheBypassed(): void
    {
        // Unset, so the width can only reach the child by being passed on
        $child = $this->withEnvironment(['COLUMNS' => null], fn (): array => $this->echo(new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true), 'staging'));

        self::assertSame(['route:lint', '--no-interaction', '--ansi', '--env=staging'], $child['arguments']);
        self::assertSame('artisan' . DIRECTORY_SEPARATOR . 'routes.php', $child['routesCache']);
        self::assertSame('97', $child['columns']);
        self::assertSame('1', $child['marker']);
    }

    /**
     * Test that a plain caller adds no optional flags.
     *
     * @return void
     */
    public function testAddsNoOptionalFlagsByDefault(): void
    {
        $child = $this->echo(new BufferedOutput, null);

        self::assertSame(['route:lint', '--no-interaction', '--no-ansi'], $child['arguments']);
    }

    /**
     * Provide each non-default verbosity with the child flag it maps to.
     *
     * @return iterable<string, array{int, string}>
     */
    public static function provideVerbosityFlags(): iterable
    {
        yield 'silent' => [OutputInterface::VERBOSITY_SILENT, '--silent'];
        yield 'quiet' => [OutputInterface::VERBOSITY_QUIET, '--quiet'];
        yield 'verbose' => [OutputInterface::VERBOSITY_VERBOSE, '-v'];
        yield 'very verbose' => [OutputInterface::VERBOSITY_VERY_VERBOSE, '-vv'];
        yield 'debug' => [OutputInterface::VERBOSITY_DEBUG, '-vvv'];
    }

    /**
     * Test that the caller's verbosity is carried over to the child.
     *
     * @param  int  $verbosity
     * @param  string  $flag
     * @return void
     */
    #[DataProvider('provideVerbosityFlags')]
    public function testCarriesOverTheCallerVerbosity(int $verbosity, string $flag): void
    {
        $child = $this->echo(new BufferedOutput($verbosity), null);

        self::assertSame(['route:lint', '--no-interaction', '--no-ansi', $flag], $child['arguments']);
    }

    /**
     * Test that the child inherits values set in the environment at runtime.
     *
     * @return void
     */
    public function testInheritsTheCallerEnvironment(): void
    {
        $child = $this->withEnvironment(['ROUTE_LINTER_INHERITED_PROBE' => 'inherited'], fn (): array => $this->echo(new BufferedOutput, null));

        self::assertSame('inherited', $child['inherited']);
    }

    /**
     * Provide environment names with the value the child receives.
     *
     * @return iterable<string, array{string|null, string, false|string}>
     */
    public static function provideEnvironmentNames(): iterable
    {
        yield 'name with its own file' => [self::ENVIRONMENT_FILE, 'probe', false];
        yield 'name without its own file' => [self::ENVIRONMENT_FILE, 'staging', 'staging'];
        yield 'cached configuration' => [null, 'probe', 'probe'];
    }

    /**
     * Test that the environment name is withheld only when it would make the
     * child load an environment file the caller did not.
     *
     * @param  string|null  $environmentFile
     * @param  string  $name
     * @param  false|string  $expected
     * @return void
     */
    #[DataProvider('provideEnvironmentNames')]
    public function testPassesOnTheEnvironmentNameUnlessItSelectsAnotherFile(?string $environmentFile, string $name, false|string $expected): void
    {
        $process = new UncachedLintProcess(self::ECHO_CHILD, $environmentFile, $this->terminal());

        $child = $this->withEnvironment(['APP_ENV' => $name], fn (): array => $this->echo(new BufferedOutput, null, $process));

        self::assertSame($expected, $child['appEnv']);
    }

    /**
     * Test that the child's exit code and output reach a quiet caller.
     *
     * @return void
     */
    public function testPassesTheChildExitCodeAndOutputThrough(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_QUIET);

        self::assertSame(3, $this->process(self::FAILING_CHILD)->run($output, null));

        $written = $output->fetch();

        self::assertStringContainsString("Linted on stdout.\n", $written);
        self::assertStringContainsString("Failed on stderr.\n", $written);
    }

    /**
     * Test that the child's error stream goes to the caller's error output.
     *
     * @return void
     */
    public function testWritesTheChildErrorStreamToTheErrorOutput(): void
    {
        $output = new BufferedConsoleOutput;

        $this->process(self::FAILING_CHILD)->run($output, null);

        self::assertSame("Linted on stdout.\n", $output->fetch());
        self::assertSame("Failed on stderr.\n", $output->errors->fetch());
    }

    /**
     * Test that a child killed by a signal exits 128 plus the signal.
     *
     * @return void
     */
    #[RequiresFunction('posix_kill')]
    public function testReportsAChildKilledBySignal(): void
    {
        $output = new BufferedConsoleOutput;

        self::assertSame(128 + SIGKILL, $this->process(self::SIGNALED_CHILD)->run($output, null));
        self::assertSame('', $output->fetch());
        self::assertSame(sprintf("The route-lint process was terminated by signal %d.\n", SIGKILL), $output->errors->fetch());
    }

    /**
     * Build a process for the given child.
     *
     * @param  string  $child
     * @return \SineMacula\RouteLinter\Console\UncachedLintProcess
     */
    private function process(string $child): UncachedLintProcess
    {
        return new UncachedLintProcess($child, self::ENVIRONMENT_FILE, $this->terminal());
    }

    /**
     * Build a terminal that is 97 columns wide.
     *
     * @return \Symfony\Component\Console\Terminal
     */
    private function terminal(): Terminal
    {
        return new class extends Terminal {
            /**
             * Get the terminal width.
             *
             * @return int
             */
            #[\Override]
            public function getWidth(): int
            {
                return 97;
            }
        };
    }

    /**
     * Run the echo child and decode what it recorded.
     *
     * @param  \Symfony\Component\Console\Output\BufferedOutput  $output
     * @param  string|null  $environment
     * @param  \SineMacula\RouteLinter\Console\UncachedLintProcess|null  $process
     * @return array{arguments: list<string>, routesCache: string, columns: string, marker: string, appEnv: false|string, inherited: false|string}
     */
    private function echo(BufferedOutput $output, ?string $environment, ?UncachedLintProcess $process = null): array
    {
        $process ??= $this->process(self::ECHO_CHILD);

        $file = tempnam(sys_get_temp_dir(), 'route-linter-echo');

        assert($file !== false);

        try {
            $this->withEnvironment(['ROUTE_LINTER_ECHO_FILE' => $file], fn () => self::assertSame(0, $process->run($output, $environment)));

            /** @var array{arguments: list<string>, routesCache: string, columns: string, marker: string, appEnv: false|string, inherited: false|string} */
            return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            unlink($file);
        }
    }

    /**
     * Run a callback with environment variables set, or unset for null.
     *
     * @template TResult
     *
     * @param  array<string, string|null>  $variables
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    private function withEnvironment(array $variables, callable $callback): mixed
    {
        $previous = [];

        foreach ($variables as $name => $value) {
            $previous[$name] = getenv($name);

            putenv($value === null ? $name : $name . '=' . $value);
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $name => $value) {
                putenv($value === false ? $name : $name . '=' . $value);
            }
        }
    }
}
