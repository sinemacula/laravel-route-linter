<?php

declare(strict_types = 1);

namespace Tests\Unit\Output;

use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\Output\ConsoleLintReporter;
use SineMacula\RouteLinter\RouteLintReport;
use SineMacula\RouteLinter\Violation;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Tests for the ConsoleLintReporter adapter.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(ConsoleLintReporter::class)]
final class ConsoleLintReporterTest extends TestCase
{
    /** @var string The line rendered for a report with no findings. */
    private const string SUCCESS_LINE = 'All routes conform to the RESTful conventions.';

    /**
     * Test that an error with a hint renders under the error header with its
     * hint.
     *
     * @return void
     */
    public function testRendersErrorFindingsWithHint(): void
    {
        $output = $this->render(new Violation('R1', Severity::ERROR, 'GET /get-users', 'get', 'use a noun-based path instead'));

        self::assertStringContainsString('Route linting errors', $output);
        self::assertStringContainsString('[R1]', $output);
        self::assertStringContainsString('GET /get-users', $output);
        self::assertStringContainsString('Hint: use a noun-based path instead', $output);
    }

    /**
     * Test that a warning renders under the warning header only.
     *
     * @return void
     */
    public function testRendersWarningFindings(): void
    {
        $output = $this->render(new Violation('R8', Severity::WARNING, 'GET users', 'users.getAll', null));

        self::assertStringContainsString('Route linting warnings', $output);
        self::assertStringContainsString('[R8]', $output);
        self::assertStringContainsString('GET users', $output);
        self::assertStringContainsString('users.getAll', $output);
        self::assertStringNotContainsString('Route linting errors', $output);
        self::assertStringNotContainsString('Stale waivers / unused suppressions', $output);
        self::assertStringNotContainsString(self::SUCCESS_LINE, $output);
    }

    /**
     * Test that an error report omits the empty warnings section.
     *
     * @return void
     */
    public function testEmptyWarningsSectionIsNotRendered(): void
    {
        $output = $this->render(new Violation('R1', Severity::ERROR, 'GET getUsers', 'getUsers', null));

        self::assertStringContainsString('Route linting errors', $output);
        self::assertStringNotContainsString('Route linting warnings', $output);
    }

    /**
     * Test that stale waivers render under their header.
     *
     * @return void
     */
    public function testRendersStaleWaivers(): void
    {
        $report = new RouteLintReport;
        $report->addStaleWaiver('orders.legacy (suppressed nothing): Old migration waiver.');

        $output = $this->renderReport($report);

        self::assertStringContainsString('Stale waivers / unused suppressions', $output);
        self::assertStringContainsString('  - orders.legacy (suppressed nothing): Old migration waiver.', $output);
    }

    /**
     * Test that a clean report renders only the success line.
     *
     * @return void
     */
    public function testCleanReportRendersOnlySuccessLine(): void
    {
        $output = $this->renderReport(new RouteLintReport);

        self::assertStringContainsString(self::SUCCESS_LINE, $output);
        self::assertStringNotContainsString('Route linting errors', $output);
        self::assertStringNotContainsString('Route linting warnings', $output);
        self::assertStringNotContainsString('Stale waivers', $output);
    }

    /**
     * Provide violations with the exact line each renders.
     *
     * @return iterable<string, array{\SineMacula\RouteLinter\Violation, string}>
     */
    public static function provideViolationLines(): iterable
    {
        yield 'with hint' => [new Violation('R5', Severity::ERROR, 'GET /fetch-items', 'fetch', 'rename to /items'), '  [R5] GET /fetch-items (fetch) -- Hint: rename to /items'];
        yield 'without hint' => [new Violation('R3', Severity::ERROR, 'POST UserItems', 'UserItems', null), '  [R3] POST UserItems (UserItems)'];
        yield 'without hint, other rule' => [new Violation('R2', Severity::ERROR, 'GET userProfiles', 'userProfiles', null), '  [R2] GET userProfiles (userProfiles)'];
    }

    /**
     * Test the exact format of a violation line, with the hint segment only
     * when there is a hint.
     *
     * @param  \SineMacula\RouteLinter\Violation  $violation
     * @param  string  $line
     * @return void
     */
    #[DataProvider('provideViolationLines')]
    public function testViolationLineFormat(Violation $violation, string $line): void
    {
        $output = $this->render($violation);

        self::assertStringContainsString($line, $output);
        self::assertSame($violation->remediationHint !== null, str_contains($output, 'Hint:'));
    }

    /**
     * Render a report holding the given violation.
     *
     * @param  \SineMacula\RouteLinter\Violation  $violation
     * @return string
     */
    private function render(Violation $violation): string
    {
        $report = new RouteLintReport;
        $report->addViolation($violation);

        return $this->renderReport($report);
    }

    /**
     * Render a report and return the output.
     *
     * @param  \SineMacula\RouteLinter\RouteLintReport  $report
     * @return string
     */
    private function renderReport(RouteLintReport $report): string
    {
        $buffer = new BufferedOutput;

        (new ConsoleLintReporter(new OutputStyle(new ArrayInput([]), $buffer)))->report($report);

        return $buffer->fetch();
    }
}
