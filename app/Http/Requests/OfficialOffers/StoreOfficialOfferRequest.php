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
        return array(
            'source_id' => array('required', 'integer', 'exists:sources,id'),
            'brand_id' => array('required', 'integer', 'exists:brands,id'),
            'model_id' => array('required', 'integer', 'exists:car_models,id'),
            'trim_name' => array('nullable', 'string', 'max:180'),
            'year' => array('nullable', 'integer', 'min:1950', 'max:' . ((int) date('Y') + 1)),
            'price_amount' => array('required', 'integer', 'min:1'),
            'currency' => array('required', 'string', 'in:UZS,USD'),
            'source_url' => array('required', 'string', 'max:1000'),
            'external_id' => array('nullable', 'string', 'max:255'),
            'valid_from' => array('nullable', 'date'),
            'valid_to' => array('nullable', 'date', 'after_or_equal:valid_from'),
        );
    }
}
