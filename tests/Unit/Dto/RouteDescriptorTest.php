<?php

declare(strict_types = 1);

namespace Tests\Unit\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use Tests\TestCase;

/**
 * Tests for the RouteDescriptor DTO.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteDescriptor::class)]
final class RouteDescriptorTest extends TestCase
{
    /**
     * Test that all four properties round-trip correctly, including isVendor
     * for both true and false, and that name may be null.
     *
     * @return void
     */
    public function testExposesUriMethodsNameAndVendorFlag(): void
    {
        // Arrange & Act - named route, non-vendor
        $named = new RouteDescriptor(
            uri: 'users/{user}',
            methods: ['GET', 'HEAD'],
            name: 'users.show',
            isVendor: false,
        );

        // Assert
        self::assertSame('users/{user}', $named->uri);
        self::assertSame(['GET', 'HEAD'], $named->methods);
        self::assertSame('users.show', $named->name);
        self::assertFalse($named->isVendor);

        // Arrange & Act - unnamed vendor route
        $vendor = new RouteDescriptor(
            uri: 'vendor/package/resource',
            methods: ['POST'],
            name: null,
            isVendor: true,
        );

        // Assert
        self::assertSame('vendor/package/resource', $vendor->uri);
        self::assertSame(['POST'], $vendor->methods);
        self::assertNull($vendor->name);
        self::assertTrue($vendor->isVendor);
    }
}
