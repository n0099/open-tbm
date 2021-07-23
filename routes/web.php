<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::view('/', 'index')->name('index');
Route::view('/post/{argu?}', 'post')->where('argu', '.*')->name('post');
Route::view('/postMulti/{argu?}', 'postMulti')->where('argu', '.*')->name('postMulti');
Route::view('/user/{argu?}', 'user')->where('argu', '.*')->name('user');
Route::view('/status', 'status')->name('status');
Route::view('/stats', 'stats')->name('stats');
Route::view('/bilibiliVote', 'bilibiliVote')->name('bilibiliVote');

// In order to use the Auth::routes() method, please install the laravel/ui package.
// Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');
