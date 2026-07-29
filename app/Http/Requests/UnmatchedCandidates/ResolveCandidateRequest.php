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
        return array(
            'brand_id' => array('required', 'integer', 'exists:brands,id'),
            'model_name' => array('required', 'string', 'max:120'),
            'model_slug' => array('required', 'string', 'max:140'),
            'production_from' => array('nullable', 'integer', 'min:1950'),
        );
    }
}
