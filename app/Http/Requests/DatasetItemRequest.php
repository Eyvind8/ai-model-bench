<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DatasetItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'input' => ['required', 'string', 'max:200000'],
            'expected_score' => ['nullable', 'integer', 'between:1,10'],
            'expected_announcement' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
