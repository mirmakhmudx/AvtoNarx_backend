<?php

namespace App\Services\Alerts;

use App\Models\IngestionBatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * TZ §15: ingestion yiqilganda ogohlantirish yuboradi. Kanallar (Slack, email)
 * ixtiyoriy — sozlanmagan bo'lsa jim o'tadi, faqat log yoziladi. Har bir kanal
 * xatosi qolganlarini buzmasligi uchun himoyalangan.
 */
class IngestionAlertService
{
    public function batchFailed(IngestionBatch $batch, string $reason): void
    {
        $message = sprintf(
            'AvtoNarx: ingestion batch #%s YIQILDI (manba_id=%s, dataset=%s). Sabab: %s',
            $batch->id,
            $batch->source_id,
            $batch->dataset,
            $reason,
        );

        try {
            Log::channel(config('alerts.log_channel', 'stack'))->error($message);
        } catch (\Throwable $e) {
            Log::error($message);
        }

        $this->sendSlack($message);
        $this->sendMail('Ingestion batch yiqildi', $message);
    }

    private function sendSlack(string $text): void
    {
        $webhook = (string) config('alerts.slack_webhook', '');

        if ($webhook === '') {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, ['text' => $text]);
        } catch (\Throwable $e) {
            Log::warning('Slack ogohlantirish yuborilmadi: '.$e->getMessage());
        }
    }

    private function sendMail(string $subject, string $body): void
    {
        $email = (string) config('alerts.email', '');

        if ($email === '') {
            return;
        }

        try {
            Mail::raw($body, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject('[AvtoNarx] '.$subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Ogohlantirish emaili yuborilmadi: '.$e->getMessage());
        }
    }
}
