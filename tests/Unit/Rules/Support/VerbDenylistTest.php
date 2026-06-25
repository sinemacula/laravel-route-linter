<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Rules\Support\VerbDenylist;
use Tests\TestCase;

/**
 * Tests for the VerbDenylist membership oracle.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(VerbDenylist::class)]
final class VerbDenylistTest extends TestCase
{
    /**
     * Test that contains() returns true for a word that is in the denylist.
     *
     * @return void
     */
    public function testContainsDenylistedVerb(): void
    {
        // Arrange
        $denylist = new VerbDenylist(['get', 'post', 'delete'], []);

        // Act & Assert
        self::assertTrue($denylist->contains('get'));
        self::assertTrue($denylist->contains('post'));
        self::assertTrue($denylist->contains('delete'));
    }

    /**
     * Test that contains() returns false for a word not in the denylist.
     *
     * @return void
     */
    public function testDoesNotContainAbsentVerb(): void
    {
        // Arrange
        $denylist = new VerbDenylist(['get', 'post'], []);

        // Act & Assert
        self::assertFalse($denylist->contains('users'));
        self::assertFalse($denylist->contains('transfer'));
    }

    /**
     * Test that constructing without a verb makes contains() return false for
     * it (homograph removal - removing from denylist is the tuning surface).
     *
     * @return void
     */
    public function testRemovingWordMakesContainsFalse(): void
    {
        // Arrange - denylist constructed without 'transfer'
        $denylist = new VerbDenylist(['get', 'post', 'delete'], []);

        // Act & Assert - 'transfer' was never added, so it must not be present
        self::assertFalse($denylist->contains('transfer'));
    }

    /**
     * Test that contains() is case-insensitive, normalising both sides to
     * lowercase.
     *
     * @return void
     */
    public function testContainsIsCaseInsensitive(): void
    {
        // Arrange
        $denylist = new VerbDenylist(['GET', 'Post'], []);

        // Act & Assert
        self::assertTrue($denylist->contains('get'));
        self::assertTrue($denylist->contains('GET'));
        self::assertTrue($denylist->contains('post'));
        self::assertTrue($denylist->contains('POST'));
    }

    /**
     * Test that hint() returns the configured remediation hint for a known
     * verb.
     *
     * @return void
     */
    public function testHintReturnsConfiguredHintOrNull(): void
    {
        // Arrange
        $denylist = new VerbDenylist(
            ['login', 'logout'],
            ['login' => 'Use POST /sessions instead.', 'logout' => 'Use DELETE /sessions/{session} instead.'],
        );

        // Act & Assert - mapped verbs return their hint
        self::assertSame('Use POST /sessions instead.', $denylist->hint('login'));
        self::assertSame('Use DELETE /sessions/{session} instead.', $denylist->hint('logout'));

        // A verb in the denylist but absent from hints returns null
        $denylistWithoutHint = new VerbDenylist(['get'], []);
        self::assertNull($denylistWithoutHint->hint('get'));
    }

    /**
     * Test that an empty denylist means contains() always returns false.
     *
     * @return void
     */
    public function testEmptyDenylistAlwaysReturnsFalse(): void
    {
        // Arrange
        $denylist = new VerbDenylist([], []);

        // Act & Assert
        self::assertFalse($denylist->contains('get'));
        self::assertFalse($denylist->contains('post'));
        self::assertFalse($denylist->contains(''));
    }

    /**
     * Test that hint() returns null for an unmapped verb regardless of denylist
     * membership.
     *
     * @return void
     */
    public function testHintReturnsNullForUnmappedVerb(): void
    {
        // Arrange - verb is in denylist but has no hint entry
        $denylist = new VerbDenylist(['fetch'], []);

        // Act & Assert
        self::assertNull($denylist->hint('fetch'));
        self::assertNull($denylist->hint('unknown'));
    }

    /**
     * Test that hint() is case-insensitive - the lookup applies
     * strtolower() so that 'LOGIN' resolves to the same hint as 'login'.
     *
     * Targets UnwrapStrToLower on hint(): without lowercasing, 'LOGIN' would
     * not match the 'login' key in the hints array and would return null
     * instead of the configured hint string.
     *
     * @return void
     */
    public function testHintIsCaseInsensitive(): void
    {
        // Arrange
        $denylist = new VerbDenylist(
            ['login'],
            ['login' => 'Use POST /sessions instead.'],
        );

        // Act & Assert - uppercase lookup must resolve the same hint as
        // lowercase
        self::assertSame('Use POST /sessions instead.', $denylist->hint('LOGIN'));
        self::assertSame('Use POST /sessions instead.', $denylist->hint('Login'));
        self::assertSame('Use POST /sessions instead.', $denylist->hint('login'));
    }
}
