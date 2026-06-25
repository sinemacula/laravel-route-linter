<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\Violation;
use Tests\TestCase;

/**
 * Tests for the Violation value object.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(Violation::class)]
final class ViolationTest extends TestCase
{
    /**
     * Test that all constructor arguments are exposed verbatim on the value
     * object.
     *
     * @return void
     */
    public function testExposesAllPropertiesAsGiven(): void
    {
        // Arrange & Act
        $violation = new Violation(
            ruleId: 'R1',
            severity: Severity::ERROR,
            routeIdentity: 'GET getUsers get-users',
            offendingSurface: 'get',
            remediationHint: 'GET /users',
        );

        // Assert
        self::assertSame('R1', $violation->ruleId);
        self::assertSame(Severity::ERROR, $violation->severity);
        self::assertSame('GET getUsers get-users', $violation->routeIdentity);
        self::assertSame('get', $violation->offendingSurface);
        self::assertSame('GET /users', $violation->remediationHint);
    }

    /**
     * Test that the remediation hint is nullable and stored as null when
     * omitted.
     *
     * @return void
     */
    public function testRemediationHintMayBeNull(): void
    {
        // Arrange & Act
        $violation = new Violation(
            ruleId: 'R5',
            severity: Severity::WARNING,
            routeIdentity: 'GET users/ users.index',
            offendingSurface: 'users/',
            remediationHint: null,
        );

        // Assert
        self::assertSame(Severity::WARNING, $violation->severity);
        self::assertNull($violation->remediationHint);
    }
}
