<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
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
     * Test that errors() returns only ERROR-severity violations and warnings()
     * returns only WARNING-severity violations.
     *
     * @return void
     */
    public function testErrorsReturnsOnlyErrorSeverityViolations(): void
    {
        // Arrange
        $report = new RouteLintReport;

        $error   = new Violation('R1', Severity::ERROR, 'GET users', 'getUsers', null);
        $warning = new Violation('R8', Severity::WARNING, 'GET users', 'users.getAll', null);

        $report->addViolation($error);
        $report->addViolation($warning);

        // Act
        $errors   = $report->errors();
        $warnings = $report->warnings();

        // Assert
        self::assertCount(1, $errors);
        self::assertSame(Severity::ERROR, $errors[0]->severity);
        self::assertSame('R1', $errors[0]->ruleId);

        self::assertCount(1, $warnings);
        self::assertSame(Severity::WARNING, $warnings[0]->severity);
        self::assertSame('R8', $warnings[0]->ruleId);
    }

    /**
     * Test that findings are returned in a deterministic total order regardless
     * of insertion order (NFR-01).
     *
     * Two report instances seeded with the same violations in different orders
     * must produce byte-identical sorted arrays.
     *
     * @return void
     */
    public function testFindingsAreDeterministicallyOrdered(): void
    {
        // Arrange - three violations that differ on all three sort keys
        $alpha = new Violation('R1', Severity::ERROR, 'GET,HEAD users', 'getUsers', null);
        $beta  = new Violation('R2', Severity::ERROR, 'GET,HEAD users', 'UserProfiles', null);
        $gamma = new Violation('R1', Severity::ERROR, 'GET articles', 'getArticles', null);

        // Report A: inserted in alpha, beta, gamma order
        $reportA = new RouteLintReport;
        $reportA->addViolation($alpha);
        $reportA->addViolation($beta);
        $reportA->addViolation($gamma);

        // Report B: inserted in shuffled gamma, alpha, beta order
        $reportB = new RouteLintReport;
        $reportB->addViolation($gamma);
        $reportB->addViolation($alpha);
        $reportB->addViolation($beta);

        // Act
        $errorsA = $reportA->errors();
        $errorsB = $reportB->errors();

        // Assert - identical count and identical identity/ruleId/surface per
        // position
        self::assertCount(3, $errorsA);
        self::assertCount(3, $errorsB);

        foreach ($errorsA as $index => $violation) {
            self::assertSame($violation->routeIdentity, $errorsB[$index]->routeIdentity);
            self::assertSame($violation->ruleId, $errorsB[$index]->ruleId);
            self::assertSame($violation->offendingSurface, $errorsB[$index]->offendingSurface);
        }

        // Assert the concrete order: gamma sorts before alpha/beta (route
        // identity 'GET articles' < 'GET,HEAD users')
        self::assertSame('GET articles', $errorsA[0]->routeIdentity);
        // Within 'GET,HEAD users': R1 before R2
        self::assertSame('R1', $errorsA[1]->ruleId);
        self::assertSame('R2', $errorsA[2]->ruleId);
    }

    /**
     * Test that a report containing only warnings and stale waivers returns
     * hasErrors() === false.
     *
     * @return void
     */
    public function testHasErrorsIsFalseForWarningOnlyReport(): void
    {
        // Arrange
        $report = new RouteLintReport;

        $report->addViolation(new Violation('R8', Severity::WARNING, 'GET users', 'users.getAll', null));
        $report->addViolation(new Violation('R11', Severity::WARNING, 'GET a/b/c/d', 'a/b/c/d', null));
        $report->addStaleWaiver('users.legacy');

        // Act & Assert
        self::assertFalse($report->hasErrors());
        self::assertCount(2, $report->warnings());
        self::assertEmpty($report->errors());
    }

    /**
     * Test that staleWaivers() returns stale entries sorted ascending
     * regardless of insertion order.
     *
     * @return void
     */
    public function testStaleWaiversAreSorted(): void
    {
        // Arrange
        $report = new RouteLintReport;

        $report->addStaleWaiver('users.legacy');
        $report->addStaleWaiver('articles.old');
        $report->addStaleWaiver('beta.route');

        // Act
        $stale = $report->staleWaivers();

        // Assert
        self::assertSame(['articles.old', 'beta.route', 'users.legacy'], $stale);
    }

    /**
     * Test that a freshly constructed report has no errors, no warnings, no
     * stale waivers, and hasErrors() returns false.
     *
     * @return void
     */
    public function testEmptyReportHasNoErrors(): void
    {
        // Arrange
        $report = new RouteLintReport;

        // Act & Assert
        self::assertFalse($report->hasErrors());
        self::assertSame([], $report->errors());
        self::assertSame([], $report->warnings());
        self::assertSame([], $report->staleWaivers());
    }

    /**
     * Test the third sort key (offendingSurface ASC) in the deterministic order
     * (kills Spaceship mutant #39: `$a->offendingSurface <=>
     * $b->offendingSurface`
     * reversed to `$b->offendingSurface <=> $a->offendingSurface`).
     *
     * Two violations share the same routeIdentity AND the same ruleId; the only
     * distinguishing key is offendingSurface. The mutant reverses this
     * comparison, producing descending order instead of ascending.
     *
     * @return void
     */
    public function testThirdSortKeyIsOffendingSurfaceAscending(): void
    {
        // Arrange - same routeIdentity and ruleId, different offendingSurface
        $report = new RouteLintReport;

        $report->addViolation(new Violation('R1', Severity::ERROR, 'GET users', 'zebra', null));
        $report->addViolation(new Violation('R1', Severity::ERROR, 'GET users', 'apple', null));
        $report->addViolation(new Violation('R1', Severity::ERROR, 'GET users', 'mango', null));

        // Act
        $errors = $report->errors();

        // Assert - must be ascending by offendingSurface
        self::assertCount(3, $errors);
        self::assertSame('apple', $errors[0]->offendingSurface);
        self::assertSame('mango', $errors[1]->offendingSurface);
        self::assertSame('zebra', $errors[2]->offendingSurface);
    }

    /**
     * Test that violations inserted in reverse offendingSurface order still
     * sort ascending (complements the spaceship test with reversed insertion
     * order).
     *
     * @return void
     */
    public function testOffendingSurfaceSortIsStableAcrossInsertionOrders(): void
    {
        // Report A: inserted ascending
        $reportA = new RouteLintReport;
        $reportA->addViolation(new Violation('R2', Severity::ERROR, 'POST orders', 'alpha-surface', null));
        $reportA->addViolation(new Violation('R2', Severity::ERROR, 'POST orders', 'omega-surface', null));

        // Report B: inserted descending
        $reportB = new RouteLintReport;
        $reportB->addViolation(new Violation('R2', Severity::ERROR, 'POST orders', 'omega-surface', null));
        $reportB->addViolation(new Violation('R2', Severity::ERROR, 'POST orders', 'alpha-surface', null));

        // Act
        $errorsA = $reportA->errors();
        $errorsB = $reportB->errors();

        // Assert - both produce the same ascending order
        self::assertSame('alpha-surface', $errorsA[0]->offendingSurface);
        self::assertSame('omega-surface', $errorsA[1]->offendingSurface);
        self::assertSame('alpha-surface', $errorsB[0]->offendingSurface);
        self::assertSame('omega-surface', $errorsB[1]->offendingSurface);
    }
}
