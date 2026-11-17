<?php

declare(strict_types = 1);

namespace Tests\Integration;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Dto\RouteDescriptor;
use SineMacula\RouteLinter\Dto\RouteSuppression;
use SineMacula\RouteLinter\Sources\RouterRouteSource;
use Tests\Fixtures\Controllers\RouteLintController;
use Tests\TestCase;

/**
 * Integration tests for the RouterRouteSource adapter.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouterRouteSource::class)]
final class RouterRouteSourceTest extends TestCase
{
    /**
     * Test that app-owned routes are mapped to descriptors.
     *
     * @return void
     */
    public function testReturnsAppOwnedRoutesAsDescriptors(): void
    {
        $router = $this->getRouter();
        $router->get('users', fn () => [])->name('users.index');
        $router->post('users', fn () => [])->name('users.store');

        $descriptors = (new RouterRouteSource($router))->appRoutes();

        self::assertContainsOnlyInstancesOf(RouteDescriptor::class, $descriptors);

        $index = $this->descriptorByName($descriptors, 'users.index');
        $store = $this->descriptorByName($descriptors, 'users.store');

        self::assertSame('users', $index->uri);
        self::assertContains('GET', $index->methods);
        self::assertFalse($index->isVendor);
        self::assertSame('users', $store->uri);
        self::assertContains('POST', $store->methods);
        self::assertFalse($store->isVendor);
    }

    /**
     * Test that a route whose controller lives under vendor is excluded.
     *
     * @return void
     */
    public function testExcludesVendorRoutes(): void
    {
        $router = $this->getRouter();
        $router->get('products', fn () => [])->name('products.index');
        $router->get('products/{product}', fn () => [])->name('products.show');
        $router->get('vendor-route', '\Illuminate\Routing\RedirectController@__invoke')->name('vendor.route');
        $router->get('vendor-redirect', '\Illuminate\Routing\RedirectController@__invoke');

        $names = array_map(static fn (RouteDescriptor $d): ?string => $d->name, (new RouterRouteSource($router))->appRoutes());

        self::assertSame(['products.index', 'products.show'], $names);
    }

    /**
     * Provide route actions defined outside vendor or not resolvable to a file.
     *
     * @return iterable<string, array{array{class-string, string}|(\Closure(): array<never>)|string}>
     */
    public static function provideAppOwnedActions(): iterable
    {
        yield 'closure' => [static fn (): array => []];
        yield 'app controller' => [[RouteLintController::class, 'index']];
        yield 'unresolvable controller' => ['App\Nonexistent\GhostController@index'];
    }

    /**
     * Test that a route the app owns is kept and not marked as vendor.
     *
     * @param  array{class-string, string}|(\Closure(): array<never>)|string  $action
     * @return void
     */
    #[DataProvider('provideAppOwnedActions')]
    public function testKeepsAppOwnedRoutes(array|\Closure|string $action): void
    {
        $router = $this->getRouter();
        $router->get('widgets', $action)->name('widgets.index');

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'widgets.index');

        self::assertFalse($descriptor->isVendor);
    }

    /**
     * Test that enumerating the same router twice gives the same routes.
     *
     * @return void
     */
    public function testEnumerationIsIdempotent(): void
    {
        $router = $this->getRouter();
        $router->get('orders', fn () => [])->name('orders.index');
        $router->delete('orders/{order}', fn () => [])->name('orders.destroy');

        $source = new RouterRouteSource($router);

        $firstUris  = array_map(static fn (RouteDescriptor $d): string => $d->uri, $source->appRoutes());
        $secondUris = array_map(static fn (RouteDescriptor $d): string => $d->uri, $source->appRoutes());

        sort($firstUris);
        sort($secondUris);

        self::assertSame($firstUris, $secondUris);
    }

    /**
     * Test that no framework route is returned in place of a descriptor.
     *
     * @return void
     */
    public function testNoFrameworkRouteLeaksPastTheAdapter(): void
    {
        $router = $this->getRouter();
        $router->get('items', fn () => [])->name('items.index');

        foreach ((new RouterRouteSource($router))->appRoutes() as $descriptor) {
            self::assertInstanceOf(RouteDescriptor::class, $descriptor);
            self::assertNotInstanceOf(Route::class, $descriptor);
        }
    }

    /**
     * Test that a router with no routes yields no descriptors.
     *
     * @return void
     */
    public function testEmptyRouterReturnsEmptyArray(): void
    {
        self::assertSame([], (new RouterRouteSource($this->getRouter()))->appRoutes());
    }

    /**
     * Provide inline suppression lists as registered on a route.
     *
     * @return iterable<string, array{list<array{list<string>, string}>}>
     */
    public static function provideInlineSuppressions(): iterable
    {
        yield 'none' => [[]];
        yield 'one' => [[[['R9'], 'Legacy naming kept for migration period.']]];
        yield 'several' => [[
            [['R1'], 'First suppression reason.'],
            [['R3'], 'Second suppression reason.'],
            [[], 'Third suppression, all rules.'],
        ]];
    }

    /**
     * Test that inline suppressions are mapped onto the descriptor in order.
     *
     * @param  list<array{list<string>, string}>  $suppressions
     * @return void
     */
    #[DataProvider('provideInlineSuppressions')]
    public function testMapsInlineSuppressions(array $suppressions): void
    {
        $router = $this->getRouter();
        $route  = $router->get('invoices', fn () => [])->name('invoices.index');

        foreach ($suppressions as [$rules, $reason]) {
            $route->ignoreRouteLint($rules, $reason); // @phpstan-ignore method.notFound
        }

        $mapped = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'invoices.index')->suppressions;

        self::assertContainsOnlyInstancesOf(RouteSuppression::class, $mapped);
        self::assertSame($suppressions, $this->unpack($mapped));
    }

    /**
     * Provide malformed lint-ignore entries with the suppressions they map to.
     *
     * @return iterable<string, array{list<mixed>, list<array{list<string>, string}>}>
     */
    public static function provideMalformedSuppressionEntries(): iterable
    {
        yield 'missing rules' => [
            [['reason' => 'No rules key present.']],
            [[[], 'No rules key present.']],
        ];
        yield 'non-array rules' => [
            [['rules' => 'not-an-array', 'reason' => 'Non-array rules value.']],
            [[[], 'Non-array rules value.']],
        ];
        yield 'keyed rules' => [
            [['rules' => ['foo' => 'R1', 'bar' => 'R3'], 'reason' => 'Re-index test.']],
            [[['R1', 'R3'], 'Re-index test.']],
        ];
        yield 'non-string rule id' => [
            [['rules' => [1], 'reason' => 'Corrupt rule ID.'], ['rules' => ['R1'], 'reason' => 'Valid entry beside a corrupt one.']],
            [[['R1'], 'Valid entry beside a corrupt one.']],
        ];
        yield 'non-string reason' => [
            [['rules' => ['R1'], 'reason' => 12345]],
            [[['R1'], '']],
        ];
        yield 'non-array entry' => [
            ['not-an-array', ['rules' => ['R1'], 'reason' => 'Valid entry beside a malformed one.']],
            [[['R1'], 'Valid entry beside a malformed one.']],
        ];
    }

    /**
     * Test that malformed lint-ignore entries are skipped or normalised.
     *
     * @param  list<mixed>  $entries
     * @param  list<array{list<string>, string}>  $expected
     * @return void
     */
    #[DataProvider('provideMalformedSuppressionEntries')]
    public function testNormalisesMalformedSuppressionEntries(array $entries, array $expected): void
    {
        $router = $this->getRouter();
        $route  = $router->get('billing', fn () => [])->name('billing.index');
        $route->setAction(array_merge($route->action, ['route-linter::lint-ignore' => $entries]));

        $mapped = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'billing.index')->suppressions;

        self::assertSame($expected, $this->unpack($mapped));
    }

    /**
     * Test that a route-cache serialised closure is an app-owned closure.
     *
     * @return void
     */
    public function testRouteWithSerializedClosureActionIsTreatedAsAppOwnedClosure(): void
    {
        $router = $this->getRouter();
        $route  = $router->get('cached-closure', fn () => [])->name('cached.closure');
        $route->setAction(array_merge($route->action, [
            'uses' => 'O:47:"Laravel\SerializableClosure\SerializableClosure":1:{s:12:"serializable";N;}',
        ]));

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'cached.closure');

        self::assertFalse($descriptor->isVendor, 'A serialized-closure route must default to app-owned.');
        self::assertNull($descriptor->handler, 'A serialized-closure route must have no handler.');
    }

    /**
     * Test that a closure route has no handler.
     *
     * @return void
     */
    public function testClosureRouteHasNullHandler(): void
    {
        $router = $this->getRouter();
        $router->get('users', fn () => [])->name('users.index');

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'users.index');

        self::assertNull($descriptor->handler);
    }

    /**
     * Test that a controller route's handler is `Class@method`.
     *
     * @return void
     */
    public function testControllerRouteHandlerIsClassAndMethod(): void
    {
        $router = $this->getRouter();
        $router->get('reports', [RouteLintController::class, 'index'])->name('reports.index');

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'reports.index');

        self::assertSame(RouteLintController::class . '@index', $descriptor->handler);
    }

    /**
     * Test that gathered middleware is a de-duplicated, zero-based list.
     *
     * @return void
     */
    public function testMiddlewareIsGatheredAsReindexedStrings(): void
    {
        $router = $this->getRouter();
        $router->get('admin', fn () => [])->name('admin.index')
            ->middleware(['auth', 'auth', 'can:manage']);

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'admin.index');

        self::assertSame(['auth', 'can:manage'], $descriptor->middleware);
    }

    /**
     * Test that closure middleware from the route action is left out.
     *
     * @return void
     */
    public function testClosureMiddlewareIsLeftOut(): void
    {
        $router = $this->getRouter();
        $router->get('reports', [
            'middleware' => ['auth', static fn (mixed $request, \Closure $next): mixed => $next($request)],
            'uses'       => fn () => [],
        ])->name('reports.index');

        $descriptor = $this->descriptorByName((new RouterRouteSource($router))->appRoutes(), 'reports.index');

        self::assertSame(['auth'], $descriptor->middleware);
    }

    /**
     * Find a descriptor by route name.
     *
     * @param  array<int, \SineMacula\RouteLinter\Dto\RouteDescriptor>  $descriptors
     * @param  string  $name
     * @return \SineMacula\RouteLinter\Dto\RouteDescriptor
     */
    private function descriptorByName(array $descriptors, string $name): RouteDescriptor
    {
        foreach ($descriptors as $descriptor) {
            if ($descriptor->name === $name) {
                return $descriptor;
            }
        }

        self::fail(sprintf('No descriptor found for route name "%s".', $name));
    }

    /**
     * Unpack suppressions into rule and reason pairs.
     *
     * @param  list<\SineMacula\RouteLinter\Dto\RouteSuppression>  $suppressions
     * @return list<array{list<string>, string}>
     */
    private function unpack(array $suppressions): array
    {
        return array_map(static fn (RouteSuppression $suppression): array => [$suppression->rules, $suppression->reason], $suppressions);
    }

    /**
     * Get the router bound to the booted application.
     *
     * @return \Illuminate\Routing\Router
     */
    private function getRouter(): Router
    {
        assert($this->app !== null);

        /** @var \Illuminate\Routing\Router */
        return $this->app->make('router');
    }
}
