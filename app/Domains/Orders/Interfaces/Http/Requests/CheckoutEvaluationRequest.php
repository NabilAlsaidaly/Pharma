<?php

namespace App\Domains\Orders\Interfaces\Http\Requests;

use Brick\Math\BigInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckoutEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        // Validate wire values: global string trimming must not normalize this contract.
        $body = json_decode($this->getContent(), true);

        return is_array($body) ? $body : [];
    }

    public function rules(): array
    {
        return ['currency' => ['required', 'string', 'in:USD,SYP'],
            'expected_cart_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->isJson() || ! is_object(json_decode($this->getContent()))) {
                $validator->errors()->add('body', 'Supply a JSON object.');
            }
            foreach (array_diff(array_keys($this->validationData()), array_keys($this->rules())) as $key) {
                $validator->errors()->add((string) $key, 'This field is not accepted.');
            }
            foreach (array_keys($this->query()) as $key) {
                $validator->errors()->add((string) $key, 'Query fields are not accepted.');
            }
            $version = $this->validationData()['expected_cart_version'] ?? null;
            if (is_string($version) && preg_match('/^(0|[1-9][0-9]{0,19})$/D', $version)
                && BigInteger::of($version)->isGreaterThan('18446744073709551615')) {
                $validator->errors()->add('expected_cart_version', 'Version exceeds its representation bound.');
            }
        }];
    }
}
