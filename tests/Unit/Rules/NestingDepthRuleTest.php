<?php

declare(strict_types = 1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\RouteLinter\Dto\RuleConfig;
use SineMacula\RouteLinter\Enums\Severity;
use SineMacula\RouteLinter\NormalisedRoute;
use SineMacula\RouteLinter\Rules\NestingDepthRule;
use Tests\TestCase;

/**
 * Tests for the NestingDepthRule (R11) warning rule.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(NestingDepthRule::class)]
final class NestingDepthRuleTest extends TestCase
{
    /** @var \SineMacula\RouteLinter\Rules\NestingDepthRule */
    private NestingDepthRule $rule;

    /** @var \SineMacula\RouteLinter\Dto\RuleConfig */
    private RuleConfig $config;

    /**
     * Set up shared fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->rule   = new NestingDepthRule;
        $this->config = new RuleConfig([], [], [], []);
    }

    /**
     * Test that a four-collection-level route produces one R11 warning
     * violation.
     *
     * users/{user}/posts/{post}/comments/{comment}/likes/{like} has four
     * literal resource segments: users, posts, comments, likes.
     *
     * @return void
     */
    public function testFourLevelRouteIsFlagged(): void
    {
        // Arrange
        $uri   = 'users/{user}/posts/{post}/comments/{comment}/likes/{like}';
        $route = new NormalisedRoute(
            uri: $uri,
            methods: ['GET'],
            name: 'users.posts.comments.likes.index',
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'likes', '{like}'],
            parameters: ['user', 'post', 'comment', 'like'],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertCount(1, $violations);
        self::assertSame('R11', $violations[0]->ruleId);
        self::assertSame(Severity::WARNING, $violations[0]->severity);
        self::assertSame($uri, $violations[0]->offendingSurface);
        self::assertNull($violations[0]->remediationHint);
    }

    /**
     * Test that a three-collection-level route produces no R11 violation.
     *
     * users/{user}/posts/{post}/comments/{comment} has three literal resource
     * segments: users, posts, comments - exactly at the threshold, so no
     * warning.
     *
     * @return void
     */
    public function testThreeLevelRouteIsNotFlagged(): void
    {
        // Arrange
        $route = new NormalisedRoute(
            uri: 'users/{user}/posts/{post}/comments/{comment}',
            methods: ['GET'],
            name: 'users.posts.comments.index',
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}'],
            parameters: ['user', 'post', 'comment'],
        );

        // Act
        $violations = $this->rule->inspect($route, $this->config);

        // Assert
        self::assertEmpty($violations);
    }

    /**
     * Test that `api` and version tokens are not counted as levels, even where
     * a parameter follows them.
     *
     * @return void
     */
    public function testApiAndVersionTokensAreNotLevels(): void
    {
        $clean = new NormalisedRoute(
            uri: 'api/{tenant}/v1/{locale}/users/{user}/posts/{post}/comments',
            methods: ['GET'],
            name: null,
            segments: ['api', '{tenant}', 'v1', '{locale}', 'users', '{user}', 'posts', '{post}', 'comments'],
            parameters: ['tenant', 'locale', 'user', 'post'],
        );
        $flagged = new NormalisedRoute(
            uri: 'api/{tenant}/v1/{locale}/users/{user}/posts/{post}/comments/{comment}/likes',
            methods: ['GET'],
            name: null,
            segments: ['api', '{tenant}', 'v1', '{locale}', 'users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'likes'],
            parameters: ['tenant', 'locale', 'user', 'post', 'comment'],
        );

        self::assertEmpty($this->rule->inspect($clean, $this->config));
        self::assertCount(1, $this->rule->inspect($flagged, $this->config));
    }

    /**
     * Test that namespace segments - literals followed by another literal - are
     * not counted as levels.
     *
     * `admin/settings` groups the routes, so only users, posts and comments are
     * collection levels.
     *
     * @return void
     */
    public function testNamespaceSegmentsAreNotLevels(): void
    {
        $route = new NormalisedRoute(
            uri: 'admin/settings/users/{user}/posts/{post}/comments/{comment}',
            methods: ['GET'],
            name: null,
            segments: ['admin', 'settings', 'users', '{user}', 'posts', '{post}', 'comments', '{comment}'],
            parameters: ['user', 'post', 'comment'],
        );

        self::assertEmpty($this->rule->inspect($route, $this->config));
    }

    /**
     * Test that route-parameter segments are not counted toward nesting depth.
     *
     * @return void
     */
    public function testParameterSegmentsAreNotCountedTowardDepth(): void
    {
        $route = new NormalisedRoute(
            uri: '{a}/{b}/users/{c}/posts/{d}/comments/{e}',
            methods: ['GET'],
            name: null,
            segments: ['{a}', '{b}', 'users', '{c}', 'posts', '{d}', 'comments', '{e}'],
            parameters: ['a', 'b', 'c', 'd', 'e'],
        );

        self::assertEmpty($this->rule->inspect($route, $this->config));
    }

    /**
     * Test that a segment merely containing a version token is counted as a
     * level.
     *
     * @return void
     */
    public function testSegmentContainingVersionStringIsCountedAsLevel(): void
    {
        $route = new NormalisedRoute(
            uri: 'users/{user}/posts/{post}/comments/{comment}/xv2',
            methods: ['GET'],
            name: null,
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'xv2'],
            parameters: ['user', 'post', 'comment'],
        );

        self::assertCount(1, $this->rule->inspect($route, $this->config));
    }

    /**
     * Test that a segment starting with a version token but carrying a suffix
     * is counted as a level.
     *
     * @return void
     */
    public function testVersionPrefixWithSuffixIsCountedAsLevel(): void
    {
        $route = new NormalisedRoute(
            uri: 'users/{user}/posts/{post}/comments/{comment}/v2extra',
            methods: ['GET'],
            name: null,
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'v2extra'],
            parameters: ['user', 'post', 'comment'],
        );

        self::assertCount(1, $this->rule->inspect($route, $this->config));
    }

    /**
     * Test that an uppercase version token is not counted as a level.
     *
     * @return void
     */
    public function testUppercaseVersionTokenIsNotALevel(): void
    {
        $route = new NormalisedRoute(
            uri: 'V2/{locale}/users/{user}/posts/{post}/comments',
            methods: ['GET'],
            name: null,
            segments: ['V2', '{locale}', 'users', '{user}', 'posts', '{post}', 'comments'],
            parameters: ['locale', 'user', 'post'],
        );

        self::assertEmpty($this->rule->inspect($route, $this->config));
    }

    /**
     * Test that a singleton resource is not counted as a level, whether
     * inferred from a singleton member route or declared in config.
     *
     * @return void
     */
    public function testSingletonResourceIsNotALevel(): void
    {
        $inferred = new NormalisedRoute(
            uri: 'users/{user}/posts/{post}/comments/{comment}/summary',
            methods: ['GET'],
            name: 'users.posts.comments.summary.show',
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'summary'],
            parameters: ['user', 'post', 'comment'],
        );
        $declared = new NormalisedRoute(
            uri: 'users/{user}/posts/{post}/comments/{comment}/summary',
            methods: ['POST'],
            name: 'users.posts.comments.summary.store',
            segments: ['users', '{user}', 'posts', '{post}', 'comments', '{comment}', 'summary'],
            parameters: ['user', 'post', 'comment'],
        );
        $config = new RuleConfig([], [], [], [], singletonResources: ['comments/{comment}/summary']);

        self::assertEmpty($this->rule->inspect($inferred, $this->config));
        self::assertCount(1, $this->rule->inspect($declared, $this->config));
        self::assertEmpty($this->rule->inspect($declared, $config));
    }

    /**
     * Test that the rule honours the configured maximum nesting depth rather
     * than a hardcoded constant.
     *
     * A two-collection-level route is clean at the default depth of 3, but a
     * config that lowers the limit to 1 must flag it, and a permissive limit of
     * 5 must leave it clean.
     *
     * @return void
     */
    public function testRespectsConfiguredMaxDepth(): void
    {
        $route = new NormalisedRoute(
            uri: 'users/{user}/posts',
            methods: ['GET'],
            name: 'users.posts.index',
            segments: ['users', '{user}', 'posts'],
            parameters: ['user'],
        );

        $strict = new RuleConfig([], [], [], [], nestingMaxDepth: 1);
        self::assertCount(1, $this->rule->inspect($route, $strict), 'Depth 2 must be flagged when the configured max depth is 1.');

        $lenient = new RuleConfig([], [], [], [], nestingMaxDepth: 5);
        self::assertCount(0, $this->rule->inspect($route, $lenient), 'Depth 2 must be clean when the configured max depth is 5.');
    }

    /**
     * Test that rule id() returns 'R11' and severity() returns
     * Severity::WARNING.
     *
     * @return void
     */
    public function testRuleMetadata(): void
    {
        self::assertSame('R11', $this->rule->id());
        self::assertSame(Severity::WARNING, $this->rule->severity());
    }
}
