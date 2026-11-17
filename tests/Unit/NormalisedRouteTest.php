<?php

declare(strict_types = 1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\NormalisedRoute;
use Tests\TestCase;

/**
 * Tests for NormalisedRoute::identity().
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(NormalisedRoute::class)]
final class NormalisedRouteTest extends TestCase
{
    /**
     * Provide routes with their expected identity.
     *
     * @return iterable<string, array{string, list<string>, string|null, string}>
     */
    public static function provideIdentities(): iterable
    {
        yield 'methods sorted' => ['users', ['POST', 'GET'], null, 'GET,POST users'];
        yield 'uri with parameter' => ['users/{user}', ['GET'], null, 'GET users/{user}'];
        yield 'name appended' => ['users', ['HEAD', 'GET'], 'users.index', 'GET,HEAD users users.index'];
        yield 'unnamed' => ['orders', ['GET'], null, 'GET orders'];
    }

    /**
     * Test that the identity is the sorted methods, the URI and any name.
     *
     * @param  string  $uri
     * @param  list<string>  $methods
     * @param  string|null  $name
     * @param  string  $expected
     * @return void
     */
    #[DataProvider('provideIdentities')]
    public function testIdentityJoinsSortedMethodsUriAndName(string $uri, array $methods, ?string $name, string $expected): void
    {
        $route = new NormalisedRoute($uri, $methods, $name, explode('/', $uri), []);

        self::assertSame($expected, $route->identity());
    }
}
