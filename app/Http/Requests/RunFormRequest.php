<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * New run / forecast / playground: a scenario, models and either a dataset or ad-hoc text.
 */
class RunFormRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scenario_id' => ['required', 'integer', 'exists:scenarios,id'],
            'dataset_id' => ['nullable', 'integer', 'exists:datasets,id'],
            'input' => ['nullable', 'string', 'max:100000', 'required_without:dataset_id'],
            'model_ids' => ['required', 'array', 'min:1'],
            'model_ids.*' => ['integer', 'exists:models,id'],
            'repeats' => ['nullable', 'integer', 'min:1', 'max:10'],
            'limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->filled('dataset_id') && $this->filled('input')) {
                $validator->errors()->add('input', 'Choose either a dataset or ad-hoc text, not both.');
            }
        }];
    }

    public function datasetId(): ?int
    {
        return $this->filled('dataset_id') ? $this->integer('dataset_id') : null;
    }

    public function adhocInput(): ?string
    {
        return $this->datasetId() === null ? (string) $this->input('input') : null;
    }

    /**
     * @return list<int>
     */
    public function modelIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('model_ids', [])));
    }
}
