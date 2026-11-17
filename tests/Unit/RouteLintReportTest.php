<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\RouteLintReport;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the RouteLintReport verdict aggregate.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteLintReport::class)]
final class RouteLintReportTest extends TestCase
{
    /**
     * Test that errors() and warnings() each return only their severity.
     *
     * @return void
     */
    public function testSeparatesErrorsFromWarnings(): void
    {
        $report = new RouteLintReport;

        $report->addViolation(new Violation('R1', Severity::ERROR, 'GET users', 'getUsers', null));
        $report->addViolation(new Violation('R8', Severity::WARNING, 'GET users', 'users.getAll', null));

        $errors   = $report->errors();
        $warnings = $report->warnings();

        self::assertCount(1, $errors);
        self::assertSame(Severity::ERROR, $errors[0]->severity);
        self::assertSame('R1', $errors[0]->ruleId);

        self::assertCount(1, $warnings);
        self::assertSame(Severity::WARNING, $warnings[0]->severity);
        self::assertSame('R8', $warnings[0]->ruleId);
    }

    /**
     * Test that findings are ordered by route identity then rule ID whatever
     * the insertion order.
     *
     * @return void
     */
    public function testFindingsAreDeterministicallyOrdered(): void
    {
        $alpha = new Violation('R1', Severity::ERROR, 'GET,HEAD users', 'getUsers', null);
        $beta  = new Violation('R2', Severity::ERROR, 'GET,HEAD users', 'UserProfiles', null);
        $gamma = new Violation('R1', Severity::ERROR, 'GET articles', 'getArticles', null);

        $reportA = new RouteLintReport;
        $reportA->addViolation($alpha);
        $reportA->addViolation($beta);
        $reportA->addViolation($gamma);

        $reportB = new RouteLintReport;
        $reportB->addViolation($gamma);
        $reportB->addViolation($alpha);
        $reportB->addViolation($beta);

        $errorsA = $reportA->errors();
        $errorsB = $reportB->errors();

        self::assertCount(3, $errorsA);
        self::assertCount(3, $errorsB);

        foreach ($errorsA as $index => $violation) {
            self::assertSame($violation->routeIdentity, $errorsB[$index]->routeIdentity);
            self::assertSame($violation->ruleId, $errorsB[$index]->ruleId);
            self::assertSame($violation->offendingSurface, $errorsB[$index]->offendingSurface);
        }

        self::assertSame('GET articles', $errorsA[0]->routeIdentity);
        self::assertSame('R1', $errorsA[1]->ruleId);
        self::assertSame('R2', $errorsA[2]->ruleId);
    }

    /**
     * Provide offending surfaces in insertion order, all on one route and rule.
     *
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function provideSurfaceOrders(): iterable
    {
        yield 'shuffled' => [['zebra', 'apple', 'mango'], ['apple', 'mango', 'zebra']];
        yield 'ascending' => [['alpha-surface', 'omega-surface'], ['alpha-surface', 'omega-surface']];
        yield 'descending' => [['omega-surface', 'alpha-surface'], ['alpha-surface', 'omega-surface']];
    }

    /**
     * Test that findings on the same route and rule are ordered by offending
     * surface.
     *
     * @param  list<string>  $inserted
     * @param  list<string>  $expected
     * @return void
     */
    #[DataProvider('provideSurfaceOrders')]
    public function testOrdersByOffendingSurfaceLast(array $inserted, array $expected): void
    {
        $report = new RouteLintReport;

        foreach ($inserted as $surface) {
            $report->addViolation(new Violation('R1', Severity::ERROR, 'GET users', $surface, null));
        }

        self::assertSame($expected, array_map(static fn (Violation $violation): string => $violation->offendingSurface, $report->errors()));
    }

    /**
     * Test that a report with only warnings and stale waivers has no errors.
     *
     * @return void
     */
    public function testHasErrorsIsFalseForWarningOnlyReport(): void
    {
        $report = new RouteLintReport;

        $report->addViolation(new Violation('R8', Severity::WARNING, 'GET users', 'users.getAll', null));
        $report->addViolation(new Violation('R11', Severity::WARNING, 'GET a/b/c/d', 'a/b/c/d', null));
        $report->addStaleWaiver('users.legacy');

        self::assertFalse($report->hasErrors());
        self::assertCount(2, $report->warnings());
        self::assertEmpty($report->errors());
    }

    /**
     * Test that stale waivers are sorted ascending.
     *
     * @return void
     */
    public function testStaleWaiversAreSorted(): void
    {
        $report = new RouteLintReport;

        $report->addStaleWaiver('users.legacy');
        $report->addStaleWaiver('articles.old');
        $report->addStaleWaiver('beta.route');

        self::assertSame(['articles.old', 'beta.route', 'users.legacy'], $report->staleWaivers());
    }

    /**
     * Test that a new report is empty and has no errors.
     *
     * @return void
     */
    public function testEmptyReportHasNoErrors(): void
    {
        $report = new RouteLintReport;

        self::assertFalse($report->hasErrors());
        self::assertSame([], $report->errors());
        self::assertSame([], $report->warnings());
        self::assertSame([], $report->staleWaivers());
    }
}
