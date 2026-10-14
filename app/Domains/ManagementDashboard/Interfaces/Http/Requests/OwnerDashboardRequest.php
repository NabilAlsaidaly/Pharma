<?php

namespace App\Domains\ManagementDashboard\Interfaces\Http\Requests;

use App\Domains\ManagementDashboard\Application\SearchTerm;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OwnerDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Existing RequireOwner runs before validation on every route.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->merge(['q' => SearchTerm::normalize($this->query('q'))]);
        }
    }

    public function rules(): array
    {
        $name = (string) $this->route()->getName();
        $keyword = ['nullable', 'string', 'min:2', 'max:100', 'regex:/[\p{L}\p{N}]/u', 'not_regex:/[\p{Cc}\p{Cf}]/u'];
        $dates = ['from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today']];
        if ($name === 'owner.dashboard') {
            return ['period' => ['sometimes', Rule::in(['today', '7days', '30days', 'month', 'custom'])], ...$dates];
        }
        if ($name === 'owner.search') {
            return ['q' => array_key_exists('q', $this->query()) ? ['required', ...array_slice($keyword, 1)] : $keyword];
        }
        $pagination = ['page' => ['sometimes', 'integer', 'min:1', 'max:1000']];
        if (str_ends_with($name, '.show')) {
            return $pagination;
        }
        $resource = $this->route('resource');
        $rules = [...$pagination, 'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])], 'q' => $keyword,
            'sort' => ['sometimes', Rule::in($resource === 'requests' ? ['newest', 'oldest'] : ['name', 'newest', 'oldest'])],
            'status' => ['nullable', Rule::in($resource === 'requests' ? ['submitted'] : ['active', 'hidden'])]];
        if ($resource === 'products') {
            $rules += ['brand' => ['nullable', 'uuid'], 'concern' => ['nullable', 'uuid'],
                'availability' => ['nullable', Rule::in(['available', 'on_request', 'unavailable'])]];
        } elseif ($resource === 'requests') {
            $rules += ['currency' => ['nullable', Rule::in(['USD', 'SYP'])], ...$dates];
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['required' => 'أدخلي قيمة مطلوبة.', 'string' => 'القيمة غير صالحة.', 'min' => 'البحث يحتاج إلى محرفين على الأقل.',
            'max' => 'القيمة تتجاوز الحد المسموح.', 'regex' => 'استخدمي نصاً واضحاً للبحث.', 'not_regex' => 'محارف النص غير مسموحة.',
            'in' => 'الخيار غير مسموح.', 'uuid' => 'معرّف السجل غير صالح.', 'integer' => 'رقم الصفحة غير صالح.',
            'date_format' => 'التاريخ غير صالح.', 'before_or_equal' => 'اختاري تاريخاً حتى اليوم.', 'after_or_equal' => 'تاريخ النهاية يجب ألا يسبق البداية.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->query()), array_keys($this->rules())) as $field) {
                $validator->errors()->add($field, 'هذا المرشح غير مسموح.');
            }
            $custom = $this->query('period') === 'custom' || $this->filled('from') || $this->filled('to');
            if (! $custom) {
                return;
            }
            if ($this->route()->getName() === 'owner.dashboard' && $this->query('period') !== 'custom') {
                $validator->errors()->add('period', 'اختاري المدة المخصصة لاستخدام تاريخي البداية والنهاية.');
            }
            if (! $this->filled('from') || ! $this->filled('to')) {
                $validator->errors()->add('from', 'اختاري تاريخي البداية والنهاية معاً.');

                return;
            }
            if (! $validator->errors()->has('from') && ! $validator->errors()->has('to')) {
                $start = CarbonImmutable::parse($this->query('from'), config('app.timezone'));
                $end = CarbonImmutable::parse($this->query('to'), config('app.timezone'));
                if ($start->diffInDays($end) > 92) {
                    $validator->errors()->add('to', 'المدة المخصصة لا تتجاوز 93 يوماً.');
                }
            }
        }];
    }
}
