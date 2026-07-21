<?php

namespace App\Http\Requests\Ingestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketListingBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array(
            'items' => array('required', 'array', 'min:1', 'max:500'),
            'items.*.source_id' => array('required', 'integer', 'exists:sources,id'),
            'items.*.external_id' => array('required', 'string', 'max:190'),
            'items.*.canonical_url' => array('required', 'string', 'max:700'),
            'items.*.brand_raw' => array('nullable', 'string', 'max:190'),
            'items.*.model_raw' => array('nullable', 'string', 'max:190'),
            'items.*.year' => array('nullable', 'integer', 'min:1950'),
            'items.*.price_amount' => array('required', 'integer', 'min:1'),
            'items.*.currency' => array('sometimes', 'string', 'size:3'),
            'items.*.condition' => array('sometimes', 'string', 'in:new,used,unknown'),
            'items.*.seller_type' => array('sometimes', 'string', 'in:private,dealer,unknown'),
            'items.*.region' => array('nullable', 'string', 'max:100'),
            'items.*.city' => array('nullable', 'string', 'max:120'),
        );
    }
}
