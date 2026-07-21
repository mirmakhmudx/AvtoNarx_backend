<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140'],
            'production_from' => ['nullable', 'integer', 'min:1950'],
            'production_to' => ['nullable', 'integer', 'gte:production_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
