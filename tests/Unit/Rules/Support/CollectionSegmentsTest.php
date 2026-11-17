<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\Support\CollectionSegments;

/**
 * Tests for the CollectionSegments locator.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(CollectionSegments::class)]
final class CollectionSegmentsTest extends TestCase
{
    /**
     * Provide URIs with the indexes of their collection segments.
     *
     * @return iterable<string, array{string, list<int>}>
     */
    public static function provideCollectionPositions(): iterable
    {
        yield 'top-level collection' => ['users', [0]];
        yield 'member route' => ['users/{user}', [0]];
        yield 'nested collection' => ['users/{user}/posts', [0, 2]];
        yield 'namespace prefixes' => ['auth/sso/connections/{connection}/domains', [2, 4]];
        yield 'parameter after an empty segment' => ['users//{user}/posts', [0, 3]];
        yield 'trailing slash' => ['users/', [0]];
        yield 'literal before an empty segment and a literal' => ['users//posts', [2]];
        yield 'parameters only' => ['{a}/{b}', []];
        yield 'empty path' => ['', []];
    }

    /**
     * Test that a literal names a collection when a parameter follows it or no
     * literal does, and that namespace literals name none.
     *
     * @param  string  $uri
     * @param  list<int>  $expected
     * @return void
     */
    #[DataProvider('provideCollectionPositions')]
    public function testLocatesCollectionSegments(string $uri, array $expected): void
    {
        self::assertSame($expected, $this->indexes($uri, null, ['GET']));
    }

    /**
     * Provide singleton member routes with their remaining collection indexes.
     *
     * @return iterable<string, array{string, string, list<string>, list<int>}>
     */
    public static function provideSingletonMemberRoutes(): iterable
    {
        yield 'show' => ['profile', 'profile.show', ['GET', 'HEAD'], []];
        yield 'update by PUT' => ['profile', 'profile.update', ['PUT'], []];
        yield 'update by PATCH' => ['profile', 'profile.update', ['PATCH'], []];
        yield 'destroy' => ['profile', 'profile.destroy', ['DELETE'], []];
        yield 'nested show' => ['connections/{connection}/callback', 'connections.callback.show', ['GET'], [0]];
        yield 'trailing slash' => ['profile/', 'profile.show', ['GET'], []];
    }

    /**
     * Test that the segment a singleton member route addresses is not a
     * collection, while its parent segments still are.
     *
     * @param  string  $uri
     * @param  string  $name
     * @param  list<string>  $methods
     * @param  list<int>  $expected
     * @return void
     */
    #[DataProvider('provideSingletonMemberRoutes')]
    public function testInfersSingletonFromMemberRoute(string $uri, string $name, array $methods, array $expected): void
    {
        self::assertSame($expected, $this->indexes($uri, $name, $methods));
    }

    /**
     * Provide routes that are not singleton member routes with their collection
     * indexes.
     *
     * @return iterable<string, array{string, string|null, list<string>, list<int>}>
     */
    public static function provideOrdinaryRoutes(): iterable
    {
        yield 'unnamed' => ['profile', null, ['GET'], [0]];
        yield 'name without an action' => ['profile', 'show', ['GET'], [0]];
        yield 'collection action' => ['profiles', 'profiles.index', ['GET'], [0]];
        yield 'store' => ['profile', 'profile.store', ['POST'], [0]];
        yield 'show for another method' => ['profile', 'profile.show', ['POST'], [0]];
        yield 'update for another method' => ['profile', 'profile.update', ['GET'], [0]];
        yield 'destroy for another method' => ['profile', 'profile.destroy', ['GET'], [0]];
        yield 'resource member show' => ['photos/{photo}', 'photos.show', ['GET'], [0]];
        yield 'resource member edit' => ['photos/{photo}/edit', 'photos.edit', ['GET'], [0, 2]];
        yield 'edit' => ['profile', 'profile.edit', ['GET'], [0]];
        yield 'empty path' => ['', 'profile.show', ['GET'], []];
    }

    /**
     * Test that routes which are not singleton member routes keep every
     * collection segment.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  list<string>  $methods
     * @param  list<int>  $expected
     * @return void
     */
    #[DataProvider('provideOrdinaryRoutes')]
    public function testDoesNotInferSingletonFromOtherRoutes(string $uri, ?string $name, array $methods, array $expected): void
    {
        self::assertSame($expected, $this->indexes($uri, $name, $methods));
    }

    /**
     * Provide routes and singleton resource paths with the remaining collection
     * indexes.
     *
     * @return iterable<string, array{string, list<string>, list<int>}>
     */
    public static function provideDeclaredSingletons(): iterable
    {
        yield 'exact path' => ['profile', ['profile'], []];
        yield 'under a prefix' => ['api/profile', ['profile'], []];
        yield 'nested path' => ['connections/{connection}/callback', ['connections/{connection}/callback'], [0]];
        yield 'parameter names differ' => ['connections/{id}/callback', ['connections/{connection}/callback'], [0]];
        yield 'surrounding slashes' => ['profile', ['/profile/'], []];
        yield 'singleton with a create segment' => ['profile/create', ['profile'], [1]];
        yield 'another parent' => ['teams/{team}/callback', ['connections/{connection}/callback'], [0, 2]];
        yield 'literal where a parameter is declared' => ['connections/all/callback', ['connections/{connection}/callback'], [2]];
        yield 'parameter where a literal is declared' => ['{connection}/callback', ['connections/callback'], [1]];
        yield 'path longer than the route' => ['callback', ['connections/{connection}/callback'], [0]];
        yield 'second of two resources' => ['settings', ['profile', 'settings'], []];
        yield 'two singletons in one route' => ['users/{user}/settings', ['users', 'users/{user}/settings'], []];
        yield 'partial segment' => ['myprofile', ['profile'], [0]];
    }

    /**
     * Test that a configured singleton resource excludes its last segment
     * wherever the path up to that segment ends with the resource path.
     *
     * @param  string  $uri
     * @param  list<string>  $resources
     * @param  list<int>  $expected
     * @return void
     */
    #[DataProvider('provideDeclaredSingletons')]
    public function testExcludesDeclaredSingletonResources(string $uri, array $resources, array $expected): void
    {
        self::assertSame($expected, $this->indexes($uri, 'profile.store', ['POST'], $resources));
    }

    /**
     * Locate the collection segments of a route built from the given parts.
     *
     * @param  string  $uri
     * @param  string|null  $name
     * @param  list<string>  $methods
     * @param  list<string>  $resources
     * @return list<int>
     */
    private function indexes(string $uri, ?string $name, array $methods, array $resources = []): array
    {
        $route  = new NormalisedRoute($uri, $methods, $name, explode('/', $uri), []);
        $config = new RuleConfig([], [], [], [], singletonResources: $resources);

        return (new CollectionSegments)->indexes($route, $config);
    }
}
