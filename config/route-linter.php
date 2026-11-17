<?php

declare(strict_types = 1);

use SineMacula\RouteLinter\Rules\ApiResourceAlignmentRule;
use SineMacula\RouteLinter\Rules\DuplicateRouteNameRule;
use SineMacula\RouteLinter\Rules\KebabCaseRule;
use SineMacula\RouteLinter\Rules\LowercaseRule;
use SineMacula\RouteLinter\Rules\NestingDepthRule;
use SineMacula\RouteLinter\Rules\PluralCollectionsRule;
use SineMacula\RouteLinter\Rules\RequiredMiddlewareRule;
use SineMacula\RouteLinter\Rules\RouteHandlerExistsRule;
use SineMacula\RouteLinter\Rules\RouteNameRule;
use SineMacula\RouteLinter\Rules\SlashSanityRule;
use SineMacula\RouteLinter\Rules\StandardMethodsRule;
use SineMacula\RouteLinter\Rules\VerbInPathRule;

$patchStatus = 'PATCH /{resources}/{resource} (set a status field)';

return [

    /*
    |---------------------------------------------------------------------------
    | Rules
    |---------------------------------------------------------------------------
    |
    | The rules the linter runs, each a class implementing the Rule or
    | AggregateRule contract and resolved from the container. Delete a line to
    | drop a built-in rule, or append your own class. Rule IDs must be unique
    | and are stable across releases, so waivers survive upgrades.
    |
    */

    'rules' => [
        VerbInPathRule::class,           // R1  - action verbs in path
        KebabCaseRule::class,            // R2  - kebab-case segments
        LowercaseRule::class,            // R3  - lowercase segments
        PluralCollectionsRule::class,    // R4  - plural collections
        SlashSanityRule::class,          // R5  - trailing/duplicate slashes
        DuplicateRouteNameRule::class,   // R6  - duplicate route name (aggregate)
        StandardMethodsRule::class,      // R7  - standard HTTP methods
        RouteNameRule::class,            // R8  - {resource}.{action} names
        ApiResourceAlignmentRule::class, // R9  - HTML-only create/edit actions
        RequiredMiddlewareRule::class,   // R10 - required middleware per pattern
        NestingDepthRule::class,         // R11 - nesting-depth smell
        RouteHandlerExistsRule::class,   // R12 - controller handler exists
    ],

    /*
    |---------------------------------------------------------------------------
    | Nesting Depth
    |---------------------------------------------------------------------------
    |
    | The maximum number of collection levels a URI may nest before R11 warns.
    | Namespace segments (such as `auth` in `auth/sessions`), singleton
    | resources, `api` and version prefixes (v1, v2, ...) are not levels.
    |
    */

    'nesting_max_depth' => 3,

    /*
    |---------------------------------------------------------------------------
    | Required Middleware
    |---------------------------------------------------------------------------
    |
    | Middleware that R10 requires on every route matching an `fnmatch` URI
    | pattern. Names are compared exactly, so write parameterised middleware in
    | full.
    |
    |   'admin/*' => ['auth', 'can:access-admin'],
    |   'api/*'   => ['auth:sanctum'],
    |
    */

    'required_middleware' => [],

    /*
    |---------------------------------------------------------------------------
    | Verb Denylist
    |---------------------------------------------------------------------------
    |
    | Action verbs that R1 flags in a path, grouped by their RESTful fix. A
    | published copy of this key replaces the package list, so leave it out of
    | your config to keep receiving additions, and clear homographs with the
    | verb allowlist below.
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

        // Generic / non-CRUD actions (rarely a resource - rethink the design)
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
    | Verb Allowlist
    |---------------------------------------------------------------------------
    |
    | Words removed from the verb denylist, for nouns spelled like a verb (a
    | `password-resets` resource, a stock `share`). Each is matched against the
    | singular form of every path word and applies to every route; waive a
    | single route with an exemption instead.
    |
    |   'verb_allowlist' => ['reset', 'transfer'],
    |
    */

    'verb_allowlist' => [],

    /*
    |---------------------------------------------------------------------------
    | Remediation Hints
    |---------------------------------------------------------------------------
    |
    | The RESTful rewrite suggested for each verb R1 flags.
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
    | Waivers for individual routes, matched by exact route name or `fnmatch`
    | URI pattern. Each needs a written reason; `rules` limits it to the given
    | rule IDs, and without it every rule is waived.
    |
    |   ['match' => 'photos.edit', 'rules' => ['R9'], 'reason' => 'Frozen API'],
    |   ['match' => 'legacy/*', 'reason' => 'Legacy surface'],
    |
    | Routes can also be waived where they are registered:
    |
    |   Route::patch('photos/{photo}/edit', ...)
    |       ->ignoreRouteLint(['R9'], 'Frozen API');
    |
    */

    'exemptions' => [],

    /*
    |---------------------------------------------------------------------------
    | Inflector Uncountables
    |---------------------------------------------------------------------------
    |
    | Words that are their own plural: R4 accepts them as collections and R1
    | does not singularise them.
    |
    */

    'uncountables' => ['media', 'data', 'series', 'information', 'news', 'feedback', 'metadata'],

    /*
    |---------------------------------------------------------------------------
    | Singleton Resources
    |---------------------------------------------------------------------------
    |
    | Resources that exist once in their context, such as a user's `profile`,
    | which R4 and R11 skip. The `show`, `update` and `destroy` routes of
    | `Route::singleton()` are recognised automatically; list the resource here
    | for the rest, such as a creatable singleton's `store` route. A path marks
    | its last segment wherever a route's path up to that point ends with it;
    | any parameter matches any parameter.
    |
    |   'singleton_resources' => ['profile', 'teams/{team}/settings'],
    |
    */

    'singleton_resources' => [],

];
