<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabelItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_score' => ['required', 'integer', 'between:1,10'],
            'expected_announcement' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
