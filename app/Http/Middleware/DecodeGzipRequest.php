<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * TZ 8.1 va parser TZ 15: parser katta (>64 KB) batch payloadlarni
 * "Content-Encoding: gzip" bilan yuboradi. Laravel/PHP so'rov tanasini
 * avtomatik ochmaydi, shuning uchun bu middleware gzip tanani ochib,
 * so'rovni ochilgan JSON bilan almashtiradi — shunda quyi qatlamlar
 * (FormRequest validatsiyasi, $request->json()) to'g'ri ma'lumot ko'radi.
 */
class DecodeGzipRequest
{
    /**
     * Ochilgan (decompressed) tananing maksimal ruxsat etilgan hajmi.
     * gzip-bomba hujumlaridan himoya. TZ 8.1: batch payload <= 5 MB, shu sabab
     * biroz zaxira bilan standart 12 MB. .env orqali sozlanadi.
     */
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
            // Bo'sh tana — ochishga hojat yo'q, faqat encoding sarlavhasini olib tashlaymiz.
            $request->headers->remove('Content-Encoding');

            return $next($request);
        }

        $decoded = @gzdecode($raw);

        if ($decoded === false) {
            return response()->json(array(
                'error' => array(
                    'code' => 'invalid_gzip',
                    'message' => 'Content-Encoding: gzip ko\'rsatilgan, lekin tanani ochib bo\'lmadi.',
                ),
            ), 400);
        }

        if (strlen($decoded) > $this->maxDecodedBytes()) {
            return response()->json(array(
                'error' => array(
                    'code' => 'payload_too_large',
                    'message' => 'Ochilgan payload ruxsat etilgan hajmdan katta.',
                ),
            ), 413);
        }

        // So'rov tanasini ochilgan kontent bilan almashtiramiz.
        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $decoded,
        );

        // initialize() sarlavhalarni server'dan qayta quradi — Content-Encoding
        // yana paydo bo'lishi mumkin, shuning uchun uni qayta olib tashlaymiz va
        // Content-Length'ni ochilgan hajmga moslaymiz.
        $request->headers->remove('Content-Encoding');
        $request->server->remove('HTTP_CONTENT_ENCODING');
        $request->headers->set('Content-Length', (string) strlen($decoded));

        // JSON so'rov bo'lsa, json bag'ini ochilgan kontent bilan yangilaymiz —
        // aks holda validatsiya eski (yoki bo'sh) keshdan o'qishi mumkin.
        if ($request->isJson()) {
            $parsed = json_decode($decoded, true);

            if (is_array($parsed)) {
                $request->setJson(new InputBag($parsed));
            }
        }

        return $next($request);
    }
}
