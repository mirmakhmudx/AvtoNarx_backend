<?php
 
namespace App\Providers;
 
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
 
class AppServiceProvider extends ServiceProvider
{
 
    public function register(): void
    {
        //
    }
 
    public function boot(): void
    {
        $this->configureRateLimiters();
    }
 
    /**
     * TZ 13-bo'lim:
     *  - public rate limit: 120 so'rov/daqiqa/IP;
     *  - ingestion rate limit: 30 so'rov/daqiqa/token.
     *
     * Limiterlar routes/api.php'da 'throttle:public-api' va 'throttle:ingestion'
     * middleware'lari orqali qo'llaniladi. Ingestion limiteri auth:sanctum'dan
     * KEYIN ishlaydi, shuning uchun $request->user() (ParserClient) va uning
     * joriy access token'i mavjud bo'ladi — kalit aynan token bo'yicha quriladi.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
 
        RateLimiter::for('ingestion', function (Request $request) {
            $user = $request->user();
            $token = $user?->currentAccessToken();
 
            // Haqiqiy (bazadagi) token — TZ bo'yicha aynan token bo'yicha kalitlaymiz.
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $key = 'ingest-token:' . $token->getKey();
            } elseif ($user !== null) {
                // TransientToken (masalan testlarda Sanctum::actingAs) — token id
                // yo'q, shuning uchun autentifikatsiya qilingan client bo'yicha
                // kalitlaymiz. Amaliyotda bitta client bitta token ishlatadi,
                // shuning uchun bu token bo'yicha kalitlashga teng keladi.
                $key = 'ingest-client:' . $user->getAuthIdentifier();
            } else {
                $key = 'ingest-ip:' . $request->ip();
            }
 
            return Limit::perMinute(30)->by($key);
        });
    }
}
