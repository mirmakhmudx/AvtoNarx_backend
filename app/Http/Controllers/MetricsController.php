<?php

namespace App\Http\Controllers;

use App\Models\IngestionBatch;
use App\Models\MarketListing;
use App\Models\MarketPriceStatistic;
use App\Models\OfficialOffer;
use App\Models\Source;
use App\Models\UnmatchedBrandModelCandidate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * TZ §17: monitoring uchun Prometheus formatidagi metrikalar.
 * Barcha qiymatlar so'rov vaqtida bazadan hisoblanadi (gauge/counter).
 * Endpoint token bilan himoyalanadi (config/metrics.php).
 */
class MetricsController extends Controller
{
    /** @var array<string, bool> */
    private array $declaredMetrics = [];

    public function __invoke(Request $request): Response
    {
        if (! config('metrics.enabled', true)) {
            return response('metrics disabled', 404);
        }

        $token = (string) config('metrics.token', '');

        if ($token !== '' && ! hash_equals($token, (string) $request->bearerToken())) {
            return response('forbidden', 403);
        }

        $this->declaredMetrics = [];
        $lines = [];

        // --- Ingestion ---
        $this->gauge($lines, 'avtonarx_ingestion_batches_total', 'Jami qabul qilingan batch\'lar', $this->safe(fn () => IngestionBatch::count()));
        $this->gauge($lines, 'avtonarx_ingestion_items_accepted_total', 'Qabul qilingan elementlar', $this->safe(fn () => (int) IngestionBatch::sum('items_accepted')));
        $this->gauge($lines, 'avtonarx_ingestion_items_rejected_total', 'Rad etilgan elementlar', $this->safe(fn () => (int) IngestionBatch::sum('items_rejected')));

        foreach (['received', 'processing', 'completed', 'partial', 'failed'] as $status) {
            $this->gauge(
                $lines,
                'avtonarx_ingestion_batches_by_status',
                'Batch\'lar holat bo\'yicha',
                $this->safe(fn () => IngestionBatch::where('status', $status)->count()),
                ['status' => $status],
            );
        }

        // --- Market listings ---
        foreach (['active', 'inactive'] as $status) {
            $this->gauge(
                $lines,
                'avtonarx_market_listings',
                'Bozor e\'lonlari holat bo\'yicha',
                $this->safe(fn () => MarketListing::where('status', $status)->count()),
                ['status' => $status],
            );
        }

        $this->gauge($lines, 'avtonarx_market_listings_pending_normalization', 'Normalizatsiya kutayotgan e\'lonlar', $this->safe(fn () => MarketListing::where('normalization_status', 'pending')->count()));

        // --- Katalog moderatsiyasi ---
        $this->gauge($lines, 'avtonarx_unmatched_candidates', 'Noaniq marka/model nomzodlari', $this->safe(fn () => UnmatchedBrandModelCandidate::count()));

        // --- Rasmiy takliflar ---
        foreach (['pending', 'published'] as $status) {
            $this->gauge(
                $lines,
                'avtonarx_official_offers',
                'Rasmiy takliflar holat bo\'yicha',
                $this->safe(fn () => OfficialOffer::where('publication_status', $status)->count()),
                ['status' => $status],
            );
        }

        // --- Statistika yoshi (eng oxirgi hisoblashdan beri o'tgan soniya) ---
        $lastCalc = null;
        try {
            $lastCalc = MarketPriceStatistic::max('calculated_at');
        } catch (\Throwable $e) {
            $lastCalc = null;
        }
        $ageSeconds = $lastCalc ? max(0, now()->getTimestamp() - Carbon::parse((string) $lastCalc)->getTimestamp()) : -1;
        $this->gauge($lines, 'avtonarx_statistics_age_seconds', 'Statistika oxirgi hisoblanganidan beri (soniya, -1=hech qachon)', $ageSeconds);
        $this->gauge($lines, 'avtonarx_statistics_rows_total', 'Statistika yozuvlari soni', $this->safe(fn () => MarketPriceStatistic::count()));

        // --- Bloklangan/eskirgan manbalar ---
        $this->gauge($lines, 'avtonarx_sources_blocked', 'Hozir bloklangan manbalar', $this->safe(fn () => Source::whereNotNull('blocked_until')->where('blocked_until', '>', now())->count()));

        $staleHours = (int) config('metrics.stale_source_hours', 24);
        $this->gauge(
            $lines,
            'avtonarx_sources_stale',
            "So'nggi {$staleHours} soatda batch yubormagan faol manbalar",
            $this->safe(fn () => Source::where('ingestion_enabled', true)
                ->whereNotIn('id', IngestionBatch::query()
                    ->where('received_at', '>=', now()->subHours($staleHours))
                    ->distinct()
                    ->pluck('source_id'))
                ->count()),
        );

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, string>  $labels
     */
    private function gauge(array &$lines, string $name, string $help, int $value, array $labels = []): void
    {
        if (! isset($this->declaredMetrics[$name])) {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";
            $this->declaredMetrics[$name] = true;
        }

        $labelStr = '';
        if ($labels !== []) {
            $parts = [];
            foreach ($labels as $k => $v) {
                $parts[] = $k.'="'.str_replace('"', '', $v).'"';
            }
            $labelStr = '{'.implode(',', $parts).'}';
        }

        $lines[] = "{$name}{$labelStr} {$value}";
    }

    /**
     * Bitta metrika xatosi butun endpointни buzmasligi uchun himoya.
     *
     * @param  callable():(int|string|null)  $callback
     */
    private function safe(callable $callback): int
    {
        try {
            $result = $callback();

            return is_numeric($result) ? (int) $result : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
