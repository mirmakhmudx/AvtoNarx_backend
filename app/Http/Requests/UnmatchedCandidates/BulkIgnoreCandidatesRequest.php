<?php

namespace App\Http\Requests\UnmatchedCandidates;

use Illuminate\Foundation\Http\FormRequest;

class BulkIgnoreCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:unmatched_brand_model_candidates,id'],
        ];
    }
}
