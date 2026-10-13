<?php

namespace App\Domains\Orders\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreliminaryHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->route('id') === null ? ['page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48']] : [];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add((string) $key, 'This field is not accepted.');
            }
            if ($this->getContent() !== '') {
                $validator->errors()->add('body', 'History accepts query parameters only.');
            }
        }];
    }
}
