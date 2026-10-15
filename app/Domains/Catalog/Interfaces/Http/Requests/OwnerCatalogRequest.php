<?php

namespace App\Domains\Catalog\Interfaces\Http\Requests;

use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Catalog\Domain\Rules\CatalogPrice;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class OwnerCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = Auth::guard('owner')->user();

        return $owner instanceof ManagementUser && $owner->role === 'owner' && $owner->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('_concerns_present') === '1' && ! $this->exists('concerns')) {
            $this->merge(['concerns' => []]);
        }
        if ($this->exists('concern_ids') && is_string($this->input('concern_ids'))) {
            $this->merge(['concerns' => preg_split('/[\s,]+/u', trim($this->input('concern_ids')), -1, PREG_SPLIT_NO_EMPTY)]);
        }
    }

    public function rules(): array
    {
        $type = (string) $this->route('type');
        $edit = $this->route('id') !== null;
        $fields = WriteCatalogRecord::FIELDS[$type] ?? [];
        $rules = ['lock_version' => [$edit ? 'required' : 'prohibited', 'regex:/^[1-9][0-9]{0,15}$/D']];
        foreach ($fields as $field) {
            $rules[$field] = match ($field) {
                'name' => [$edit ? 'sometimes' : 'required', 'string', 'max:255', 'regex:/\S/u'],
                'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[\pL\pN]+(?:[-_][\pL\pN]+)*$/uD', Rule::unique($type, 'slug')->ignore($this->route('id'))],
                'brand_id' => [$edit ? 'sometimes' : 'required', 'uuid', Rule::exists(Brand::class, 'id')->whereNull('deleted_at')],
                'status' => ['sometimes', Rule::in(['active', 'hidden'])],
                'availability_status' => ['sometimes', Rule::in(['available', 'on_request', 'unavailable'])],
                'is_recommended' => ['sometimes', 'boolean'],
                'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
                'price_usd', 'price_syp' => ['sometimes', 'nullable', function ($attribute, $value, $fail): void {
                    try {
                        if (! is_string($value)) {
                            throw new InvalidArgumentException;
                        }
                        CatalogPrice::normalize($value);
                    } catch (InvalidArgumentException) {
                        $fail('استخدمي سعراً عشرياً غير سالب حتى منزلتين، أو اتركيه دون تحديد.');
                    }
                }],
                default => ['sometimes', 'nullable', 'string', 'max:20000'],
            };
        }
        if ($type === 'variants' && ! $edit) {
            $rules['parent'] = ['required', 'uuid'];
        }
        if ($type === 'products') {
            $rules['_concerns_present'] = ['sometimes', Rule::in(['1'])];
            $rules['concern_ids'] = ['sometimes', 'nullable', 'string', 'max:4000'];
            $rules['concerns'] = ['sometimes', 'array', 'max:100'];
            $rules['concerns.*'] = ['uuid', 'distinct', Rule::exists(Concern::class, 'id')];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = array_merge(array_keys($this->rules()), ['_token', '_method']);
        $validator->after(function (Validator $validator) use ($allowed): void {
            if (array_diff(array_keys($this->all()), $allowed) !== []) {
                $validator->errors()->add('fields', 'تتضمن البيانات حقولاً غير مسموحة.');
            }
            foreach ($this->all() as $value) {
                if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value))) {
                    $validator->errors()->add('fields', 'تتضمن البيانات محارف غير صالحة.');
                }
            }
        });
    }

    public function messages(): array
    {
        return ['required' => 'هذا الحقل مطلوب.', 'unique' => 'الرابط محجوز لسجل آخر، بما فيه السجلات المؤرشفة.', 'exists' => 'السجل المحدد غير متاح.', 'uuid' => 'المعرّف غير صالح.', 'in' => 'القيمة المحددة غير مسموحة.'];
    }
}
