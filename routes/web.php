<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/', function () {
    return view('index');
})->name('index');

Route::get('/console', function() {
    return view('console');
})->name('console');

Route::post('/api/dbcommand', 'App\Http\Controllers\DatabaseCommandController@Command')->name('console__dbcommand');

Route::get('/bot', 'App\Http\Controllers\BotController@Submit')->name('bot-vk');

