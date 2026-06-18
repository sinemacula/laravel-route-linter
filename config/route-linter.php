<?php

use SineMacula\RouteLinter\Rules\ApiResourceAlignmentRule;
use SineMacula\RouteLinter\Rules\KebabCaseRule;
use SineMacula\RouteLinter\Rules\LowercaseRule;
use SineMacula\RouteLinter\Rules\NestingDepthRule;
use SineMacula\RouteLinter\Rules\PluralCollectionsRule;
use SineMacula\RouteLinter\Rules\RouteNameRule;
use SineMacula\RouteLinter\Rules\SlashSanityRule;
use SineMacula\RouteLinter\Rules\StandardMethodsRule;
use SineMacula\RouteLinter\Rules\VerbInPathRule;

// Canonical fix shared by every state-transition verb: PATCH the resource and set a field.
$patchStatus = 'PATCH /{resources}/{resource} (set a status field)';

return [

    /*
    |---------------------------------------------------------------------------
    | Rules
    |---------------------------------------------------------------------------
    |
    | The ordered rule set the engine runs over every route. Each entry is a
    | class implementing SineMacula\RouteLinter\Contracts\Rule and is resolved
    | from the container, so rules may declare constructor dependencies. Remove
    | a built-in rule by deleting its line; add your own by appending its class.
    | Rule IDs must be unique (the engine asserts this at boot). The shipped IDs
    | are R1-R5, R7-R9, R11; R6 and R10 are intentionally reserved/retired.
    |
    */

    'rules' => [
        VerbInPathRule::class,           // R1  — action verbs in path
        KebabCaseRule::class,            // R2  — kebab-case segments
        LowercaseRule::class,            // R3  — lowercase segments
        PluralCollectionsRule::class,    // R4  — plural collections
        SlashSanityRule::class,          // R5  — trailing/duplicate slashes
        StandardMethodsRule::class,      // R7  — standard HTTP methods
        RouteNameRule::class,            // R8  — {resource}.{action} names
        ApiResourceAlignmentRule::class, // R9  — HTML-only create/edit actions
        NestingDepthRule::class,         // R11 — nesting-depth smell
    ],

    /*
    |---------------------------------------------------------------------------
    | Nesting Depth
    |---------------------------------------------------------------------------
    |
    | The maximum number of collection levels a URI may nest before the
    | nesting-depth rule (R11) raises a warning. `api` and version prefixes
    | (v1, v2, …) are excluded from the count.
    |
    */

    'nesting_max_depth' => 3,

    /*
    |---------------------------------------------------------------------------
    | Verb Denylist
    |---------------------------------------------------------------------------
    |
    | Action verbs that flag a path segment, grouped by their canonical fix. The
    | list is curated and tunable: add or remove words. Removing a word here is
    | rule tuning, NOT a per-route exemption — use it for legitimate domain-noun
    | homographs (e.g. a "transfer" resource, a stock "share", a chess "move").
    |
    */

    'verb_denylist' => [

        // Read / query (use GET on the collection or resource)
        'get', 'list', 'fetch', 'find', 'view', 'show', 'search', 'check', 'count', 'download', 'export',

        // Create (use POST on the collection)
        'create', 'add', 'store', 'save', 'generate', 'import', 'upload', 'register',

        // Update / replace (use PUT or PATCH on the resource)
        'update', 'edit', 'set', 'apply', 'toggle', 'sync', 'merge', 'move', 'copy', 'transfer',

        // Delete (use DELETE on the resource)
        'delete', 'remove',

        // State transitions (PATCH the resource, setting a field)
        'activate', 'deactivate', 'enable', 'disable', 'approve', 'reject', 'accept', 'decline',
        'publish', 'unpublish', 'archive', 'restore', 'confirm', 'complete', 'cancel',
        'start', 'stop', 'pause', 'resume', 'lock', 'unlock', 'suspend', 'reopen',
        'assign', 'reset', 'revoke',

        // Generic / non-CRUD actions (rarely a resource — rethink the design)
        'do', 'run', 'execute', 'perform', 'trigger', 'handle', 'process', 'calculate', 'validate', 'verify', 'refresh',

        // Auth & sessions
        'login', 'logout',

        // Messaging, commerce & social actions
        'send', 'submit', 'notify', 'invite', 'subscribe', 'unsubscribe', 'redeem', 'checkout',
        'purchase', 'pay', 'share', 'like', 'follow', 'unfollow', 'vote', 'rate', 'review',
        'bookmark', 'favorite',
    ],

    /*
    |---------------------------------------------------------------------------
    | Remediation Hints
    |---------------------------------------------------------------------------
    |
    | Per-verb RESTful-rewrite hints surfaced on each verb violation.
    |
    */

    'remediation_hints' => [
        'login'      => 'POST /auth',
        'logout'     => 'DELETE /auth',
        'register'   => 'POST /users',
        'get'        => 'GET /{resources}',
        'list'       => 'GET /{resources}',
        'fetch'      => 'GET /{resources}/{resource}',
        'show'       => 'GET /{resources}/{resource}',
        'create'     => 'POST /{resources} (create a new resource)',
        'add'        => 'POST /{resources} (add a resource)',
        'store'      => 'POST /{resources} (store a resource)',
        'update'     => 'PUT /{resources}/{resource} or PATCH /{resources}/{resource}',
        'edit'       => 'PUT /{resources}/{resource} or PATCH /{resources}/{resource}',
        'delete'     => 'DELETE /{resources}/{resource}',
        'remove'     => 'DELETE /{resources}/{resource}',
        'cancel'     => $patchStatus,
        'activate'   => $patchStatus,
        'deactivate' => $patchStatus,
        'search'     => 'GET /{resources}?q=',
        'check'      => 'GET /{resources}/{resource} (read the status field)',
        'process'    => $patchStatus,
        'submit'     => $patchStatus,
        'send'       => $patchStatus,
        'transfer'   => 'PATCH /{resources}/{resource}, or model the transfer as its own resource',
        'download'   => 'GET /{resources}/{resource} (return the file via content negotiation)',
        'upload'     => 'POST /{resources} or PUT /{resources}/{resource}',
        'import'     => 'POST /{resources}',
        'export'     => 'GET /{resources} (negotiate the format, e.g. Accept: text/csv)',
        'validate'   => 'POST /{resources} (validation belongs to create; reject with 422)',
        'generate'   => 'POST /{resources}',
        'refresh'    => 'POST /{resources} or re-GET /{resources}/{resource}',
    ],

    /*
    |---------------------------------------------------------------------------
    | Exemption Allowlist
    |---------------------------------------------------------------------------
    |
    | Ships EMPTY. Each entry waives one route and MUST carry a written reason.
    | Keyed by route name or URI pattern (fnmatch wildcards). An optional
    | per-rule `rules` key scopes the waiver to specific rule ids; omit it to
    | waive every rule on the matched route.
    |
    |   ['match' => 'photos.edit', 'rules' => ['R9'], 'reason' => 'BL-123 frozen contract'],
    |   ['match' => 'legacy.*',                       'reason' => 'BL-200 legacy surface'],
    |
    | Routes can also be waived inline at registration via the route macro:
    |
    |   Route::patch('photos/{photo}/edit', ...)->ignoreRouteLint(['R9'], 'reason');
    |
    */

    'exemptions' => [],

    /*
    |---------------------------------------------------------------------------
    | Inflector Uncountables
    |---------------------------------------------------------------------------
    |
    | Words the plural-collections rule treats as already-plural (so they are
    | not flagged as singular collections).
    |
    */

    'uncountables' => ['media', 'data', 'series', 'information', 'news', 'feedback', 'metadata'],

];
