<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * TZ 14-bo'lim: bu trait ulangan model o'zgarsa (yaratildi/yangilandi/
 * o'chirildi), audit_logs'ga yozuv qo'shadi — LEKIN faqat harakat admin
 * (User) tomonidan bo'lsa. Ingestion/parser (ParserClient token) yoki anonim
 * yozuvlar audit qilinmaydi, aks holda jurnal avtomatik yozuvlar bilan to'lib
 * ketardi.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('created', null, $model->redactAuditValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();
            unset($changes['updated_at'], $changes['created_at']);

            if (empty($changes)) {
                return;
            }

            $old = array();
            foreach (array_keys($changes) as $key) {
                $old[$key] = $model->getOriginal($key);
            }

            $model->writeAuditLog('updated', $model->redactAuditValues($old), $model->redactAuditValues($changes));
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('deleted', $model->redactAuditValues($model->getOriginal()), null);
        });
    }

    protected function writeAuditLog(string $action, ?array $old, ?array $new): void
    {
        $actor = $this->resolveAuditActor();

        if (! $actor instanceof User) {
            return;
        }

        $request = request();

        AuditLog::create(array(
            'user_id' => $actor->getKey(),
            'action' => $action,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
        ));
    }

    protected function resolveAuditActor()
    {
        $request = request();

        if ($request !== null && $request->user() !== null) {
            return $request->user();
        }

        return Auth::user();
    }

    /**
     * Maxfiy maydonlarni jurnaldan yashiramiz.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function redactAuditValues(array $values): array
    {
        foreach (array('password', 'remember_token', 'api_token') as $secret) {
            if (array_key_exists($secret, $values)) {
                $values[$secret] = '[redacted]';
            }
        }

        return $values;
    }
}
