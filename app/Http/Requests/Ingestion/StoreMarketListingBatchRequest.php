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
            'batch_id' => array('required', 'uuid'),
            'source' => array('required', 'string', 'exists:sources,code'),
            'mode' => array('required', 'string', 'in:incremental,snapshot,offline_html'),
            'parser_version' => array('nullable', 'string', 'max:50'),
            'collected_at' => array('required', 'date'),
            'items' => array('required', 'array', 'min:1', 'max:500'),
            'items.*.external_id' => array('required', 'string', 'max:255'),
            'items.*.url' => array('required', 'string', 'max:1000'),
            'items.*.brand' => array('required', 'string', 'max:180'),
            'items.*.model' => array('required', 'string', 'max:180'),
            'items.*.year' => array('required', 'integer', 'min:1950', 'max:' . ((int) date('Y') + 1)),
            'items.*.price.amount' => array('required', 'integer', 'min:1'),
            'items.*.price.currency' => array('required', 'string', 'in:UZS,USD'),
            'items.*.observed_at' => array('required', 'date'),
            'items.*.published_at' => array('nullable', 'date'),
            'items.*.location.region' => array('nullable', 'string', 'max:100'),
            'items.*.location.city' => array('nullable', 'string', 'max:120'),
            'items.*.condition' => array('sometimes', 'string', 'in:used,new,unknown'),
            'items.*.seller_type' => array('sometimes', 'string', 'in:private,dealer,unknown'),
            'items.*.content_hash' => array('required', 'string', 'size:64'),
        );
    }
}
