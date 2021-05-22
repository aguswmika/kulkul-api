<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\KulkulController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\ParameterController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group([
    'prefix' => 'v1'
], function () {
    Route::get('/location', [LocationController::class, 'index']);
    Route::get('/location/{id}/desa', [LocationController::class, 'indexDesa']);
    Route::get('/location/{type}/{id}', [LocationController::class, 'indexById']);

    Route::get('/kulkul/{id}/desa', [KulkulController::class, 'showByDesa']);
    Route::get('/kulkul/{id}/banjar', [KulkulController::class, 'showByBanjar']);
    Route::get('/kulkul/{id}/pura', [KulkulController::class, 'showByPura']);

    Route::get('/category', [CategoryController::class, 'index']);

    Route::get('/params', [ParameterController::class, 'index']);
    
    Route::post('/search', [SearchController::class, 'index']);
});