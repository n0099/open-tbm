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
Route::view('/query/{argu?}', 'query')->where('argu', '.*')->name('query');
Route::view('/status', 'status')->name('status');
Route::view('/stats', 'stats')->name('stats');

Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');
