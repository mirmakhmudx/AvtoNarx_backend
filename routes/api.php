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
use App\Http\Middleware\DecodeGzipRequest;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public API — TZ 13: 120 so'rov/daqiqa/IP
    Route::middleware('throttle:public-api')->group(function () {
        Route::get('brands', [PublicBrandController::class, 'index']);
        Route::get('brands/{slug}', [PublicBrandController::class, 'show']);
        Route::get('brands/{brandSlug}/models', [PublicCarModelController::class, 'index']);
        Route::get('models/{carModel}/prices', [ModelPriceController::class, 'index']);
        Route::get('filters', [FilterController::class, 'index']);
    });

    // Parser / Ingestion API — Sanctum token bilan himoyalangan.
    // TZ 13: 30 so'rov/daqiqa/token. throttle auth'dan KEYIN turadi — shunda
    // limiter token bo'yicha kalitlay oladi.
    // DecodeGzipRequest eng oldinda: parser gzip yuborsa (TZ 8.1), tanani ochib
    // beradi, shunda validatsiya to'g'ri JSON ko'radi.
    Route::middleware([
        DecodeGzipRequest::class,
        'auth:sanctum',
        'throttle:ingestion',
    ])->prefix('ingestion')->group(function () {
        Route::post('market-listings/batches', [IngestionController::class, 'storeMarketListingsBatch']);
        Route::post('official-offers/batches', [IngestionController::class, 'storeOfficialOffersBatch']);
        Route::get('batches/{batchId}', [IngestionController::class, 'showBatch']);
        Route::get('batches/{batchId}/errors', [IngestionController::class, 'batchErrors']);
        Route::post('heartbeat', [IngestionController::class, 'heartbeat']);
        Route::get('catalog', [IngestionController::class, 'catalog']);
    });

    // Admin API — Sanctum + Policy bilan himoyalangan
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::post('brands', [AdminBrandController::class, 'store']);
        Route::put('brands/{brand}', [AdminBrandController::class, 'update']);
        Route::delete('brands/{brand}', [AdminBrandController::class, 'destroy']);

        Route::post('car-models', [AdminCarModelController::class, 'store']);
        Route::put('car-models/{carModel}', [AdminCarModelController::class, 'update']);

        Route::get('official-offers/pending', [AdminOfficialOfferController::class, 'pending']);
        Route::post('official-offers', [AdminOfficialOfferController::class, 'store']);
        Route::post('official-offers/{officialOffer}/publish', [AdminOfficialOfferController::class, 'publish']);
        Route::post('official-offers/{officialOffer}/reject', [AdminOfficialOfferController::class, 'reject']);

        Route::get('unmatched-candidates', [UnmatchedCandidateController::class, 'index']);
        Route::get('unmatched-candidates/counts-by-brand', [UnmatchedCandidateController::class, 'countsByBrand']);
        Route::post('unmatched-candidates/{unmatchedCandidate}/resolve', [UnmatchedCandidateController::class, 'resolve']);
        Route::post('unmatched-candidates/{unmatchedCandidate}/ignore', [UnmatchedCandidateController::class, 'ignore']);
        Route::post('unmatched-candidates/bulk-ignore', [UnmatchedCandidateController::class, 'bulkIgnore']);
        Route::post('unmatched-candidates/ignore-all-pending', [UnmatchedCandidateController::class, 'ignoreAllPending']);
    });
});
