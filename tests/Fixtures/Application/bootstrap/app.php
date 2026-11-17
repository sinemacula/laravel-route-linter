<?php

declare(strict_types = 1);

use Illuminate\Foundation\Application;
use SineMacula\RouteLinter\RouteLinterServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([RouteLinterServiceProvider::class])
    ->withRouting(web: __DIR__ . '/../routes/web.php')
    ->withExceptions()
    ->create();
