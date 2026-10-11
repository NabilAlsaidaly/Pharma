<?php

namespace App\Http\Requests;

use App\Domains\Cart\Domain\Rules\CartQuantity;
use Brick\Math\BigInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PersonalCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }
        if ($this->isMethod('GET')) {
            return $this->is('api/v1/favorites/check') ? ['product_id' => ['required', 'uuid']]
                : ['page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:48']];
        }
        if ($this->is('api/v1/favorites')) {
            return ['product_id' => ['required', 'uuid']];
        }
        $quantity = ['required', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! CartQuantity::valid($value)) {
                $fail('Quantity must be a positive JSON integer within the technical bound.');
            }
        }];
        if ($this->isMethod('POST')) {
            return ['product_id' => ['required', 'uuid'], 'variant_id' => ['sometimes', 'nullable', 'uuid'], 'quantity' => $quantity];
        }

        return ['expected_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D'],
            'quantity' => array_merge(['sometimes'], $quantity), 'variant_id' => ['sometimes', 'nullable', 'uuid']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'This field is not accepted.');
            }
            if ($this->isMethod('PATCH')) {
                if ($this->exists('quantity') === $this->exists('variant_id')) {
                    $validator->errors()->add('selection', 'Supply exactly one of quantity or variant_id.');
                }
                $version = $this->input('expected_version');
                if (is_string($version) && preg_match('/^(0|[1-9][0-9]{0,19})$/D', $version)
                    && BigInteger::of($version)->isGreaterThan('18446744073709551615')) {
                    $validator->errors()->add('expected_version', 'Version exceeds its representation bound.');
                }
            }
        }];
    }
}
