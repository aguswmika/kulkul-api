<?php

use Illuminate\Support\Facades\Hash;
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
    return view('welcome');
});

Route::get('/passwords', function () {
    return trim(preg_replace('/(?<!\ )[A-Z]/', ' $0', 'AsaaaasasAss'));
});


Route::get('/password', function(){
    return Hash::make('informatika');
});