<?php

/*
|--------------------------------------------------------------------------
| Ogohlantirishlar (§15) — ingestion yiqilishi haqida xabar
|--------------------------------------------------------------------------
| Barcha kanallar ixtiyoriy. Sozlanmagan bo'lsa — jim (faqat log qoladi).
*/

return [
    'slack_webhook' => env('ALERT_SLACK_WEBHOOK', ''),
    'email' => env('ALERT_EMAIL', ''),
    'log_channel' => env('ALERT_LOG_CHANNEL', 'stack'),
];
