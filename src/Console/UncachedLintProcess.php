<?php

declare(strict_types = 1);

namespace SineMacula\RouteLinter\Console;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

/**
 * Runs `route:lint` in a child process that bypasses the route cache.
 *
 * A cached route table has lost what the rules depend on: closures are
 * serialised, so their defining file no longer marks them as vendor routes, and
 * unnamed routes carry random generated names. The child boots as a fresh
 * artisan command would, with the route cache pointed at a file that does not
 * exist, so it lints the current route definitions. Running it in its own
 * process keeps the fresh application's global state out of the caller. The
 * child's output is streamed through as rendered and its exit code returned.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
final readonly class UncachedLintProcess
{
    /** @var string Environment variable marking the child, so a cache it cannot bypass fails rather than recursing. */
    public const string MARKER = 'ROUTE_LINTER_UNCACHED';

    /** @var array<int, string> The child's verbosity flag for each output verbosity. */
    private const array VERBOSITY_FLAGS = [
        OutputInterface::VERBOSITY_SILENT       => '--silent',
        OutputInterface::VERBOSITY_QUIET        => '--quiet',
        OutputInterface::VERBOSITY_VERBOSE      => '-v',
        OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
        OutputInterface::VERBOSITY_DEBUG        => '-vvv',
    ];

    /**
     * Create a new uncached lint process.
     *
     * @param  string  $basePath
     * @param  string|null  $environmentFile
     * @param  \Symfony\Component\Console\Terminal  $terminal
     */
    public function __construct(

        /** The application base path, holding the artisan script the child runs */
        private string $basePath,

        /** The environment file the caller loaded, or null when its configuration is cached and none was loaded */
        private ?string $environmentFile,

        /** The caller's terminal, whose width the child renders at */
        private Terminal $terminal = new Terminal,
    ) {}

    /**
     * Run the child to completion, streaming its output, and return its exit
     * code.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $environment
     * @return int
     */
    public function run(OutputInterface $output, ?string $environment): int
    {
        $process = new Process($this->command($output, $environment), $this->basePath, $this->environment(), null, null);

        try {
            return $process->run(fn (string $type, string $chunk) => $this->stream($output, $type, $chunk));
        } catch (ProcessSignaledException $exception) {
            $this->errorOutput($output)->writeln(sprintf('<error>The route-lint process was terminated by signal %d.</error>', $exception->getSignal()));

            return 128 + $exception->getSignal();
        }
    }

    /**
     * Build the child's command line, carrying over the caller's output
     * decoration, verbosity, and any explicitly selected environment.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string|null  $environment
     * @return list<string>
     */
    private function command(OutputInterface $output, ?string $environment): array
    {
        $command = [PHP_BINARY, $this->basePath . DIRECTORY_SEPARATOR . 'artisan', 'route:lint', '--no-interaction', $output->isDecorated() ? '--ansi' : '--no-ansi'];

        if (isset(self::VERBOSITY_FLAGS[$output->getVerbosity()])) {
            $command[] = self::VERBOSITY_FLAGS[$output->getVerbosity()];
        }

        if ($environment !== null) {
            $command[] = '--env=' . $environment;
        }

        return $command;
    }

    /**
     * Build the child's environment.
     *
     * The child inherits the caller's environment, which already holds the
     * values the caller loaded from its environment file. An inherited
     * `APP_ENV` would also make the child load that environment's own file,
     * which the caller did not, so it is withheld whenever such a file exists
     * beside the one the caller loaded; the child then reads the same value
     * from the caller's file. A caller with cached configuration loaded no
     * file, so its `APP_ENV` is always passed on. The route cache path is
     * relative, so it resolves under the base path on every platform, and sits
     * beneath the artisan script, a file, so it can never exist.
     *
     * @return array<string, false|string>
     */
    private function environment(): array
    {
        $environment = getenv();

        if ($this->environmentFile !== null && isset($environment['APP_ENV']) && is_file($this->environmentFile . '.' . $environment['APP_ENV'])) {
            $environment['APP_ENV'] = false;
        }

        return [
            ...$environment,
            'APP_ROUTES_CACHE' => 'artisan' . DIRECTORY_SEPARATOR . 'routes.php',
            'COLUMNS'          => (string) $this->terminal->getWidth(),
            self::MARKER       => '1',
        ];
    }

    /**
     * Write a chunk of the child's output as rendered, to the error output for
     * the child's error stream when the caller has one.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @param  string  $type
     * @param  string  $chunk
     * @return void
     */
    private function stream(OutputInterface $output, string $type, string $chunk): void
    {
        $target = $type === Process::ERR ? $this->errorOutput($output) : $output;

        // The child already applied its verbosity, so the chunk is written at
        // every level but silent.
        $target->write($chunk, false, OutputInterface::OUTPUT_RAW | OutputInterface::VERBOSITY_QUIET);
    }

    /**
     * Resolve the caller's error output, falling back to its standard output.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    private function errorOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
