<?php

use App\Http\Controllers\PostsQuery;
use App\Http\Controllers\UsersQuery;
use App\Http\Middleware\ReCAPTCHACheck;
use Illuminate\Support\Facades\Route;

Route::get('/forums', static fn () => \App\Eloquent\Model\Forum::all()->toArray());

Route::middleware(ReCAPTCHACheck::class)->group(static function () {
    Route::get('/posts', [PostsQuery::class, 'query']);
    Route::get('/users', [UsersQuery::class, 'query']);
});
