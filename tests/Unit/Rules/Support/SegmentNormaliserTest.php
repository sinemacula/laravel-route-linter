<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SineMacula\RouteLinter\Contracts\Inflector;
use SineMacula\RouteLinter\Rules\Support\SegmentNormaliser;
use Tests\TestCase;

/**
 * Tests for the SegmentNormaliser pipeline.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(SegmentNormaliser::class)]
final class SegmentNormaliserTest extends TestCase
{
    /**
     * Provide URIs and uncountables with the words they reduce to.
     *
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function provideUris(): iterable
    {
        yield 'api, version and parameter dropped' => ['api/v1/{user}/getUsers', [], ['get', 'user']];
        yield 'camelCase' => ['getUsers', [], ['get', 'user']];
        yield 'kebab-case' => ['user-profiles', [], ['user', 'profile']];
        yield 'snake_case' => ['get_users', [], ['get', 'user']];
        yield 'mixed delimiters' => ['get_userProfiles', [], ['get', 'user', 'profile']];
        yield 'nothing but prefixes and parameters' => ['api/v1/{user}', [], []];
        yield 'empty' => ['', [], []];
        yield 'uncountable' => ['medias', ['medias'], ['medias']];
        yield 'optional parameter' => ['users/{user?}/posts', [], ['user', 'post']];
        yield 'brace with a suffix' => ['{id}extra/posts', [], ['{id}extra', 'post']];
        yield 'version token mid-segment' => ['av2/users', [], ['av2', 'user']];
        yield 'version token with a suffix' => ['v1more/users', [], ['v1more', 'user']];
        yield 'uppercase version token' => ['V1/users', [], ['user']];
        yield 'uppercase api' => ['API/users', [], ['user']];
        yield 'leading and trailing delimiters' => ['_users-', [], ['user']];
    }

    /**
     * Test that a URI reduces to its lowercase, singular words, without
     * parameters, version tokens, or the `api` prefix.
     *
     * @param  string  $uri
     * @param  list<string>  $uncountables
     * @param  list<string>  $words
     * @return void
     */
    #[DataProvider('provideUris')]
    public function testReducesUriToCandidateWords(string $uri, array $uncountables, array $words): void
    {
        $inflector = new class implements Inflector {
            /**
             * Return the singular form of a value.
             *
             * @param  string  $value
             * @return string
             */
            #[\Override]
            public function singular(string $value): string
            {
                return str_ends_with($value, 's') ? substr($value, 0, -1) : $value;
            }

            /**
             * Determine whether the value is plural.
             *
             * @param  string  $value
             * @return bool
             */
            #[\Override]
            public function isPlural(string $value): bool
            {
                return str_ends_with($value, 's');
            }
        };

        self::assertSame($words, (new SegmentNormaliser($inflector))->normalise($uri, $uncountables));
    }
}
