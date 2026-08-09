<?php

namespace App\Http\Requests\Ingestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfficialOfferBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'uuid'],
            'source' => ['required', 'string', 'exists:sources,code'],
            'mode' => ['required', 'string', 'in:incremental,snapshot,offline_html'],
            'parser_version' => ['nullable', 'string', 'max:50'],
            'collected_at' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.external_id' => ['required', 'string', 'max:255'],
            'items.*.url' => ['required', 'string', 'max:1000'],
            'items.*.brand' => ['required', 'string', 'max:180'],
            'items.*.model' => ['required', 'string', 'max:180'],
            'items.*.trim' => ['nullable', 'string', 'max:180'],
            'items.*.year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            'items.*.price.amount' => ['required', 'integer', 'min:1'],
            'items.*.price.currency' => ['required', 'string', 'in:UZS,USD'],
            'items.*.observed_at' => ['required', 'date'],
            'items.*.valid_from' => ['nullable', 'date'],
            'items.*.valid_to' => ['nullable', 'date', 'after_or_equal:items.*.valid_from'],
            'items.*.content_hash' => ['required', 'string', 'size:64'],
        ];
    }
}
