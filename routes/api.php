<?php

use App\Http\Controllers\Api\V1\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\CarModelController as AdminCarModelController;
use App\Http\Controllers\Api\V1\Admin\OfficialOfferController as AdminOfficialOfferController;
use App\Http\Controllers\Api\V1\Parser\IngestionController;
use App\Http\Controllers\Api\V1\Public\BrandController as PublicBrandController;
use App\Http\Controllers\Api\V1\Public\CarModelController as PublicCarModelController;
use App\Http\Controllers\Api\V1\Public\ModelPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public API
    Route::get('brands', array(PublicBrandController::class, 'index'));
    Route::get('brands/{slug}', array(PublicBrandController::class, 'show'));
    Route::get('brands/{brandSlug}/models', array(PublicCarModelController::class, 'index'));
    Route::get('models/{carModel}/prices', array(ModelPriceController::class, 'index'));

    // Parser API — Sanctum token bilan himoyalangan
    Route::middleware('auth:sanctum')->prefix('ingestion')->group(function () {
        Route::post('market-listings/batches', array(IngestionController::class, 'storeMarketListingsBatch'));
    });

    // Admin API — endi Sanctum + Policy bilan himoyalangan
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::post('brands', array(AdminBrandController::class, 'store'));
        Route::put('brands/{brand}', array(AdminBrandController::class, 'update'));
        Route::delete('brands/{brand}', array(AdminBrandController::class, 'destroy'));

        Route::post('car-models', array(AdminCarModelController::class, 'store'));
        Route::put('car-models/{carModel}', array(AdminCarModelController::class, 'update'));

        Route::get('official-offers/pending', array(AdminOfficialOfferController::class, 'pending'));
        Route::post('official-offers', array(AdminOfficialOfferController::class, 'store'));
        Route::post('official-offers/{officialOffer}/publish', array(AdminOfficialOfferController::class, 'publish'));
        Route::post('official-offers/{officialOffer}/reject', array(AdminOfficialOfferController::class, 'reject'));
    });
});
