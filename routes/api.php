<?php

use App\Http\Controllers\Api\V1\AuthController;
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
    Route::post('/login', [AuthController::class, 'login']);
    Route::group([
        'middleware' => 'auth:sanctum'
    ], function(){
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/params/dimension', [ParameterController::class, 'indexDimension']);
        Route::get('/params/direction', [ParameterController::class, 'indexDirection']);
        Route::get('/params/activity', [ParameterController::class, 'indexActivity']);
        Route::get('/params/kabupaten', [ParameterController::class, 'indexKabupaten']);
        Route::get('/params/pengangge', [ParameterController::class, 'indexPengangge']);
        Route::get('/params/kabupaten/{id}/kecamatan', [ParameterController::class, 'indexKecamatan']);
        Route::get('/params/kecamatan/{id}/desa', [ParameterController::class, 'indexDesa']);

        Route::post('/kulkul', [KulkulController::class, 'store']);
    });

    Route::get('/location', [LocationController::class, 'index']);
    
    Route::get('/location/pura', [LocationController::class, 'indexPura']);
    Route::get('/location/pura/{id}/desa', [LocationController::class, 'indexPuraDesa']);

    Route::get('/location/{id}/desa', [LocationController::class, 'indexDesa']);
    Route::get('/location/{type}/{id}', [LocationController::class, 'indexById']);

    Route::get('/kulkul/{id}/desa', [KulkulController::class, 'showByDesa']);
    Route::get('/kulkul/{id}/banjar', [KulkulController::class, 'showByBanjar']);
    Route::get('/kulkul/{id}/pura', [KulkulController::class, 'showByPura']);

    Route::get('/category', [CategoryController::class, 'index']);

    Route::post('/search', [SearchController::class, 'index']);
    
    Route::get('/params', [ParameterController::class, 'index']);
});