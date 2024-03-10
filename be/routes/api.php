<?php

use App\Http\Controllers\PostsQuery;
use App\Http\Controllers\UsersQuery;
use App\Http\Middleware\ReCAPTCHACheck;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/forums', static fn () => \App\Eloquent\Model\Forum::all()->toJson());

Route::middleware(ReCAPTCHACheck::class)->group(static function () {
    Route::get('/posts', [PostsQuery::class, 'query']);
    Route::get('/users', [UsersQuery::class, 'query']);
});
