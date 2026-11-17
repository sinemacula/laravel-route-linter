<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * Provide denylists, allowed words and a word with its expected membership.
     *
     * @return iterable<string, array{list<string>, list<string>, string, bool}>
     */
    public static function provideMembership(): iterable
    {
        yield 'listed' => [['get', 'post', 'delete'], [], 'post', true];
        yield 'first listed' => [['get', 'post', 'delete'], [], 'get', true];
        yield 'last listed' => [['get', 'post', 'delete'], [], 'delete', true];
        yield 'not listed' => [['get', 'post'], [], 'users', false];
        yield 'homograph not listed' => [['get', 'post'], [], 'transfer', false];
        yield 'uppercase listing' => [['GET', 'Post'], [], 'get', true];
        yield 'uppercase word' => [['GET', 'Post'], [], 'GET', true];
        yield 'mixed-case listing' => [['GET', 'Post'], [], 'post', true];
        yield 'uppercase word for a mixed-case listing' => [['GET', 'Post'], [], 'POST', true];
        yield 'allowed' => [['get', 'Reset', 'cancel'], ['RESET', 'archive'], 'reset', false];
        yield 'not allowed' => [['get', 'Reset', 'cancel'], ['RESET', 'archive'], 'get', true];
        yield 'last not allowed' => [['get', 'Reset', 'cancel'], ['RESET', 'archive'], 'cancel', true];
        yield 'allowed but never listed' => [['get', 'Reset', 'cancel'], ['RESET', 'archive'], 'archive', false];
        yield 'empty denylist' => [[], [], 'get', false];
        yield 'empty word' => [[], [], '', false];
    }

    /**
     * Test that membership is case-insensitive and excludes allowed words.
     *
     * @param  list<string>  $verbs
     * @param  list<string>  $allowed
     * @param  string  $word
     * @param  bool  $expected
     * @return void
     */
    #[DataProvider('provideMembership')]
    public function testContains(array $verbs, array $allowed, string $word, bool $expected): void
    {
        self::assertSame($expected, (new VerbDenylist($verbs, [], $allowed))->contains($word));
    }

    /**
     * Provide words with their expected hint.
     *
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideHints(): iterable
    {
        yield 'hinted' => ['login', 'Use POST /sessions instead.'];
        yield 'second hinted' => ['logout', 'Use DELETE /sessions/{session} instead.'];
        yield 'uppercase' => ['LOGIN', 'Use POST /sessions instead.'];
        yield 'capitalised' => ['Login', 'Use POST /sessions instead.'];
        yield 'listed without a hint' => ['fetch', null];
        yield 'not listed' => ['unknown', null];
    }

    /**
     * Test that the hint lookup is case-insensitive and null for a verb with no
     * hint.
     *
     * @param  string  $word
     * @param  string|null  $hint
     * @return void
     */
    #[DataProvider('provideHints')]
    public function testHint(string $word, ?string $hint): void
    {
        $denylist = new VerbDenylist(
            ['login', 'logout', 'fetch'],
            ['login' => 'Use POST /sessions instead.', 'logout' => 'Use DELETE /sessions/{session} instead.'],
        );

        self::assertSame($hint, $denylist->hint($word));
    }
}
