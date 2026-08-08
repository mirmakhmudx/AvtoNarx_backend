<?php

namespace App\Http\Requests\OfficialOffers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin panel orqali muharrir (content editor) tomonidan QO'LDA rasmiy
 * taklif kiritish uchun. Bu TZ 8.3'dagi parser ingestion endpoint'idan
 * FARQLI — bu yerda avtorizatsiya qilingan foydalanuvchi to'g'ridan-to'g'ri
 * OfficialOfferController::store() orqali yozadi (moderatsiya navbatiga,
 * "pending" holatida — OfficialOfferService::create() buni avtomatik
 * qo'yadi).
 */
class StoreOfficialOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_id' => ['required', 'integer', 'exists:sources,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'model_id' => ['required', 'integer', 'exists:car_models,id'],
            'trim_name' => ['nullable', 'string', 'max:180'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'price_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'in:UZS,USD'],
            'source_url' => ['required', 'string', 'max:1000'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }
}
