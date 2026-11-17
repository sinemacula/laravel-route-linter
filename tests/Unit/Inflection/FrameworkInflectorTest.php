<?php

declare(strict_types = 1);

namespace Tests\Unit\Inflection;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Inflection\FrameworkInflector;
use Tests\TestCase;

/**
 * Tests for FrameworkInflector.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(FrameworkInflector::class)]
final class FrameworkInflectorTest extends TestCase
{
    /**
     * Test that singular() returns the singular form of a regular plural word.
     *
     * @return void
     */
    public function testSingularisesAPlural(): void
    {
        $inflector = new FrameworkInflector;

        self::assertSame('user', $inflector->singular('users'));
    }

    /**
     * Test that a configured uncountable is kept by singular() and is plural.
     *
     * @return void
     */
    public function testUncountableIsReturnedUnchangedAndPluralSafe(): void
    {
        $inflector = new FrameworkInflector(['media']);

        self::assertSame('media', $inflector->singular('media'));
        self::assertTrue($inflector->isPlural('media'));
    }

    /**
     * Test that isPlural() tells plural words from singular ones.
     *
     * @return void
     */
    public function testIsPluralDetectsPlurality(): void
    {
        $inflector = new FrameworkInflector;

        self::assertTrue($inflector->isPlural('users'));
        self::assertFalse($inflector->isPlural('user'));
    }

    /**
     * Test that an empty string is kept by singular() and is not plural.
     *
     * @return void
     */
    public function testEmptyStringEdgeCases(): void
    {
        $inflector = new FrameworkInflector;

        self::assertSame('', $inflector->singular(''));
        self::assertFalse($inflector->isPlural(''));
    }

    /**
     * Test that uncountable matching is case-insensitive.
     *
     * @return void
     */
    public function testUncountableMatchingIsCaseInsensitive(): void
    {
        $media = new FrameworkInflector(['media']);
        $data  = new FrameworkInflector(['data']);

        self::assertSame('Media', $media->singular('Media'));
        self::assertTrue($media->isPlural('MEDIA'));
        self::assertTrue($data->isPlural('DATA'));
        self::assertTrue($data->isPlural('Data'));
    }

    /**
     * Test that an uncountable whose singular form is itself is still plural.
     *
     * @return void
     */
    public function testUncountableWhoseSingularFormMatchesItselfIsPlural(): void
    {
        self::assertTrue((new FrameworkInflector(['data']))->isPlural('data'));
    }
}
