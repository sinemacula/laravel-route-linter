<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Severity;
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
class ViolationTest extends TestCase
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
        static::assertSame('R1', $violation->ruleId);
        static::assertSame(Severity::ERROR, $violation->severity);
        static::assertSame('GET getUsers get-users', $violation->routeIdentity);
        static::assertSame('get', $violation->offendingSurface);
        static::assertSame('GET /users', $violation->remediationHint);
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
        static::assertSame(Severity::WARNING, $violation->severity);
        static::assertNull($violation->remediationHint);
    }
}
