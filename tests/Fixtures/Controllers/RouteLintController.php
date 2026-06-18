<?php

namespace Tests\Fixtures\Controllers;

/**
 * Minimal fixture controller for route linter integration tests.
 *
 * Provides a named action method so the route linter adapter can resolve the
 * controller class file and confirm it is app-owned (not under vendor).
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
class RouteLintController
{
    /**
     * Handle the incoming request as an invokable controller.
     *
     * @return array<string, bool>
     */
    public function __invoke(): array
    {
        return ['ok' => true];
    }

    /**
     * Handle the incoming request.
     *
     * @return array<string, bool>
     */
    public function index(): array
    {
        return ['ok' => true];
    }
}
