<?php

use App\Http\Controllers;
use App\Http\Middleware\ReCAPTCHACheck;
use Illuminate\Support\Facades\Route;

Route::get('/forums', static fn() => \App\Eloquent\Model\Forum::all());
Route::middleware(ReCAPTCHACheck::class)->group(static function () {
    Route::get('/posts', [Controllers\PostsQuery::class, 'query']);
    Route::get('/users', [Controllers\UsersQuery::class, 'query']);
});
