<?php

namespace App\Services\PublicApi;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiCacheService
{
    public function respond(string $cacheKey, Request $request, \Closure $resolver, ?int $ttlSeconds = null): JsonResponse
    {
        $ttl = $ttlSeconds ?? (int) config('public_api.cache_ttl_seconds', 300);
        $store = (string) config('public_api.cache_store', 'redis');

        $cached = Cache::store($store)->remember($cacheKey, $ttl, function () use ($resolver) {
            return [
                'payload' => $resolver(),
                'generated_at' => now()->toIso8601String(),
            ];
        });

        $generatedAt = Carbon::parse($cached['generated_at']);
        $etag = '"'.hash('sha256', $cacheKey.'|'.$cached['generated_at']).'"';

        if ($this->isNotModified($request, $etag, $generatedAt)) {
            return response()->json(null, 304)
                ->header('ETag', $etag)
                ->header('Last-Modified', $generatedAt->toRfc7231String())
                ->header('Cache-Control', 'public, max-age='.$ttl);
        }

        return response()->json($cached['payload'])
            ->header('ETag', $etag)
            ->header('Last-Modified', $generatedAt->toRfc7231String())
            ->header('Cache-Control', 'public, max-age='.$ttl);
    }

    public function forget(string $cacheKey): void
    {
        Cache::store((string) config('public_api.cache_store', 'redis'))->forget($cacheKey);
    }

    private function isNotModified(Request $request, string $etag, Carbon $generatedAt): bool
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return true;
        }

        $ifModifiedSince = $request->headers->get('If-Modified-Since');

        if ($ifModifiedSince !== null) {
            try {
                $since = Carbon::parse($ifModifiedSince);

                if ($generatedAt->lessThanOrEqualTo($since)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Noto'g'ri formatdagi sarlavha — e'tiborsiz qoldiramiz.
            }
        }

        return false;
    }
}
