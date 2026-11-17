<?php

declare(strict_types = 1);

namespace Tests\Unit\Support;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Exceptions\InvalidConfigurationException;
use SineMacula\RouteLinter\Support\RouteLintMacros;
use Tests\TestCase;

/**
 * Tests for the RouteLintMacros registrar.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(RouteLintMacros::class)]
final class RouteLintMacrosTest extends TestCase
{
    /**
     * Test that the service provider registers the macro.
     *
     * @return void
     */
    public function testMacroIsRegisteredByTheProvider(): void
    {
        self::assertTrue(Route::hasMacro('ignoreRouteLint'));
    }

    /**
     * Test that register() can be called repeatedly.
     *
     * @return void
     */
    public function testRegistrationIsIdempotent(): void
    {
        RouteLintMacros::register();
        RouteLintMacros::register();

        self::assertTrue(Route::hasMacro('ignoreRouteLint'));
    }

    /**
     * Test that registering again leaves the existing macro working.
     *
     * @return void
     */
    public function testSecondRegisterCallKeepsTheMacroWorking(): void
    {
        RouteLintMacros::register();

        $router = $this->getRouter();
        $route  = $router->get('idempotent-test', fn () => []);

        $route->ignoreRouteLint(['R1'], 'Still works after second register.'); // @phpstan-ignore method.notFound

        $action = $route->getAction('route-linter::lint-ignore');

        self::assertIsArray($action);
        self::assertCount(1, $action);
        self::assertSame(['R1'], $action[0]['rules']);
        self::assertSame('Still works after second register.', $action[0]['reason']);
    }

    /**
     * Test that the macro appends an entry to the route action and returns the
     * route.
     *
     * @return void
     */
    public function testMacroAppendsEntryToActionKey(): void
    {
        $router = $this->getRouter();
        $route  = $router->get('users', fn () => [])->name('users.index');

        $result = $route->ignoreRouteLint(['R9'], 'Legacy naming kept for backward compatibility.'); // @phpstan-ignore method.notFound

        $action = $route->getAction('route-linter::lint-ignore');

        self::assertIsArray($action);
        self::assertCount(1, $action);
        self::assertSame(['R9'], $action[0]['rules']);
        self::assertSame('Legacy naming kept for backward compatibility.', $action[0]['reason']);
        self::assertSame($route, $result);
    }

    /**
     * Test that repeated calls accumulate entries.
     *
     * @return void
     */
    public function testMultipleCallsAccumulateEntries(): void
    {
        $router = $this->getRouter();
        $route  = $router->get('orders', fn () => []);

        $route->ignoreRouteLint(['R9'], 'First suppression.'); // @phpstan-ignore method.notFound
        $route->ignoreRouteLint([], 'Second suppression, all rules.'); // @phpstan-ignore method.notFound

        $action = $route->getAction('route-linter::lint-ignore');

        self::assertIsArray($action);
        self::assertCount(2, $action);
        self::assertSame(['R9'], $action[0]['rules']);
        self::assertSame('First suppression.', $action[0]['reason']);
        self::assertSame([], $action[1]['rules']);
        self::assertSame('Second suppression, all rules.', $action[1]['reason']);
    }

    /**
     * Provide reasons that are blank.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideBlankReasons(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
    }

    /**
     * Test that a blank reason is rejected.
     *
     * @param  string  $reason
     * @return void
     */
    #[DataProvider('provideBlankReasons')]
    public function testBlankReasonIsRejected(string $reason): void
    {
        $route = $this->getRouter()->get('items', fn () => []);

        $this->expectException(InvalidConfigurationException::class);

        $route->ignoreRouteLint(['R9'], $reason); // @phpstan-ignore method.notFound
    }

    /**
     * Test that an empty rules list is stored as given, meaning all rules.
     *
     * @return void
     */
    public function testEmptyRulesArrayMeansSuppressAll(): void
    {
        $router = $this->getRouter();
        $route  = $router->get('resources', fn () => []);

        $route->ignoreRouteLint([], 'Suppress all rules for this route.'); // @phpstan-ignore method.notFound

        $action = $route->getAction('route-linter::lint-ignore');

        self::assertIsArray($action);
        self::assertSame([], $action[0]['rules']);
    }

    /**
     * Test that the stored rules are re-indexed as a list.
     *
     * @return void
     */
    public function testStoredRulesListIsAlwaysReIndexed(): void
    {
        $router = $this->getRouter();
        $route  = $router->get('assets', fn () => []);

        $route->ignoreRouteLint(['foo' => 'R1', 'bar' => 'R3'], 'Non-sequential keys.'); // @phpstan-ignore method.notFound

        $action = $route->getAction('route-linter::lint-ignore');

        self::assertIsArray($action);
        self::assertCount(1, $action);
        self::assertSame([0 => 'R1', 1 => 'R3'], $action[0]['rules']);
    }

    /**
     * Test that register() binds the macro when it is absent.
     *
     * The macro registry is static, so it is flushed here and restored after.
     *
     * @return void
     */
    public function testRegisterBindsMacroWhenAbsent(): void
    {
        $registry = new \ReflectionProperty(Route::class, 'macros');
        $saved    = $registry->getValue();

        try {
            Route::flushMacros();
            self::assertFalse(Route::hasMacro('ignoreRouteLint'));

            RouteLintMacros::register();

            self::assertTrue(Route::hasMacro('ignoreRouteLint'));

            $route  = $this->getRouter()->get('flush-test', fn () => []);
            $result = $route->ignoreRouteLint(['R1'], 'Re-registered macro still returns the route.'); // @phpstan-ignore method.notFound

            self::assertSame($route, $result);
            self::assertIsArray($route->getAction('route-linter::lint-ignore'));
        } finally {
            $registry->setValue(null, $saved);
        }
    }

    /**
     * Get the application router.
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
