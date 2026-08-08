<?php

namespace App\Http\Requests\UnmatchedCandidates;

use Illuminate\Foundation\Http\FormRequest;

class ResolveCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'model_name' => ['required', 'string', 'max:120'],
            'model_slug' => ['required', 'string', 'max:140'],
            'production_from' => ['nullable', 'integer', 'min:1950'],
        ];
    }
}
