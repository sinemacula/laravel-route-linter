<?php

declare(strict_types = 1);

use Illuminate\Support\Facades\Route;

Route::get('users', fn () => [])->name('users.index');
Route::get('posts', fn () => []);
Route::get('user/{user}', fn () => [])->name('user.show');
