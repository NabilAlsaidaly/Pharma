<?php

namespace App\Domains\Orders\Interfaces\Http\Requests;

use Brick\Math\BigInteger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreliminaryCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        $body = json_decode($this->getContent(), true);
        $data = is_array($body) ? $body : [];
        if (isset($data['contact']) && is_array($data['contact'])) {
            foreach ($data['contact'] as &$value) {
                if (is_string($value)) {
                    $value = preg_replace('/^\s+|\s+$/u', '', $value);
                }
            }
            unset($value);
            $data['contact']['notes'] ??= null;
            if ($data['contact']['notes'] === '') {
                $data['contact']['notes'] = null;
            }
        }
        if (isset($data['operation_id']) && is_string($data['operation_id'])) {
            $data['operation_id'] = strtolower($data['operation_id']);
        }

        return $data;
    }

    public function rules(): array
    {
        $text = fn (int $max): array => ['required', 'string', 'max:'.$max, 'regex:/[\p{L}\p{N}]/u'];

        return ['operation_id' => ['required', 'string', 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[47][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D'],
            'currency' => ['required', 'string', 'in:USD,SYP'],
            'expected_cart_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D'],
            'contact' => ['required', 'array:full_name,phone,governorate,city,address,notes'],
            'contact.full_name' => $text(150), 'contact.governorate' => $text(120), 'contact.city' => $text(120), 'contact.address' => $text(500),
            'contact.phone' => ['required', 'string', 'max:40', 'regex:/^\+?[\p{Nd} ().\/-]+$/uD', 'regex:/(?:\p{Nd}[^\p{Nd}]*){3}/u'],
            'contact.notes' => ['nullable', 'string', 'max:1000']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $raw = json_decode($this->getContent());
            if (! $this->isJson() || ! is_object($raw) || ! is_object($raw->contact ?? null)) {
                $validator->errors()->add('body', 'Supply JSON objects for body and contact.');
            }
            foreach (array_diff(array_keys($this->validationData()), ['operation_id', 'currency', 'expected_cart_version', 'contact']) as $key) {
                $validator->errors()->add((string) $key, 'This field is not accepted.');
            }
            foreach (array_keys($this->query()) as $key) {
                $validator->errors()->add((string) $key, 'Query fields are not accepted.');
            }
            foreach ((array) ($raw->contact ?? []) as $key => $value) {
                if (is_string($value) && preg_match('/[\p{Cc}\p{Cf}<>]/u', $value)) {
                    $validator->errors()->add('contact.'.$key, 'Control characters and markup are not accepted.');
                }
            }
            $v = $this->validationData()['expected_cart_version'] ?? null;
            if (is_string($v) && preg_match('/^(0|[1-9][0-9]{0,19})$/D', $v) && BigInteger::of($v)->isGreaterThan('18446744073709551615')) {
                $validator->errors()->add('expected_cart_version', 'Version exceeds its representation bound.');
            }
        }];
    }
}
