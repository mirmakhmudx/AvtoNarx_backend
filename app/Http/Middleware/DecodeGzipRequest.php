<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\InputBag;

class DecodeGzipRequest
{

    protected function maxDecodedBytes(): int
    {
        return (int) config('ingestion.max_decoded_bytes', 12 * 1024 * 1024);
    }

    public function handle(Request $request, Closure $next)
    {
        $encoding = strtolower((string) $request->headers->get('Content-Encoding', ''));

        if (! str_contains($encoding, 'gzip')) {
            return $next($request);
        }

        $raw = $request->getContent();

        if ($raw === '') {
            $request->headers->remove('Content-Encoding');

            return $next($request);
        }

        $decoded = @gzdecode($raw);

        if ($decoded === false) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_gzip',
                    'message' => 'Content-Encoding: gzip ko\'rsatilgan, lekin tanani ochib bo\'lmadi.',
                ],
            ], 400);
        }

        if (strlen($decoded) > $this->maxDecodedBytes()) {
            return response()->json([
                'error' => [
                    'code' => 'payload_too_large',
                    'message' => 'Ochilgan payload ruxsat etilgan hajmdan katta.',
                ],
            ], 413);
        }

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $decoded,
        );


        $request->headers->remove('Content-Encoding');
        $request->server->remove('HTTP_CONTENT_ENCODING');
        $request->headers->set('Content-Length', (string) strlen($decoded));

        if ($request->isJson()) {
            $parsed = json_decode($decoded, true);

            if (is_array($parsed)) {
                $request->setJson(new InputBag($parsed));
            }
        }

        return $next($request);
    }
}
