<?php

use App\Http\Controllers\Api\V1\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\CarModelController as AdminCarModelController;
use App\Http\Controllers\Api\V1\Admin\OfficialOfferController as AdminOfficialOfferController;
use App\Http\Controllers\Api\V1\Admin\UnmatchedCandidateController;
use App\Http\Controllers\Api\V1\Parser\IngestionController;
use App\Http\Controllers\Api\V1\Public\BrandController as PublicBrandController;
use App\Http\Controllers\Api\V1\Public\CarModelController as PublicCarModelController;
use App\Http\Controllers\Api\V1\Public\FilterController;
use App\Http\Controllers\Api\V1\Public\ModelPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public API — TZ 13-bo'lim: 120 so'rov/daqiqa/IP
    Route::middleware('throttle:public-api')->group(function () {
        Route::get('brands', array(PublicBrandController::class, 'index'));
        Route::get('brands/{slug}', array(PublicBrandController::class, 'show'));
        Route::get('brands/{brandSlug}/models', array(PublicCarModelController::class, 'index'));
        Route::get('models/{carModel}/prices', array(ModelPriceController::class, 'index'));
        Route::get('filters', array(FilterController::class, 'index'));
    });

    // Parser / Ingestion API — Sanctum token bilan himoyalangan,
    // TZ 13-bo'lim: 30 so'rov/daqiqa/token
    Route::middleware(array('auth:sanctum', 'throttle:ingestion'))->prefix('ingestion')->group(function () {
        Route::post('market-listings/batches', array(IngestionController::class, 'storeMarketListingsBatch'));
        Route::post('official-offers/batches', array(IngestionController::class, 'storeOfficialOffersBatch'));
        Route::get('batches/{batchId}', array(IngestionController::class, 'showBatch'));
        Route::get('batches/{batchId}/errors', array(IngestionController::class, 'batchErrors'));
        Route::post('heartbeat', array(IngestionController::class, 'heartbeat'));
        Route::get('catalog', array(IngestionController::class, 'catalog'));
    });

    // Admin API — Sanctum + Policy bilan himoyalangan
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

        Route::get('unmatched-candidates', array(UnmatchedCandidateController::class, 'index'));
        Route::get('unmatched-candidates/counts-by-brand', array(UnmatchedCandidateController::class, 'countsByBrand'));
        Route::post('unmatched-candidates/{unmatchedCandidate}/resolve', array(UnmatchedCandidateController::class, 'resolve'));
        Route::post('unmatched-candidates/{unmatchedCandidate}/ignore', array(UnmatchedCandidateController::class, 'ignore'));
        Route::post('unmatched-candidates/bulk-ignore', array(UnmatchedCandidateController::class, 'bulkIgnore'));
        Route::post('unmatched-candidates/ignore-all-pending', array(UnmatchedCandidateController::class, 'ignoreAllPending'));
    });
});
