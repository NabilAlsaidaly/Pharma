<?php

namespace App\Domains\Orders\Application\Review;

use App\Domains\ManagementDashboard\Application\DashboardDateRange;
use App\Domains\ManagementDashboard\Application\SearchTerm;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use Illuminate\Validation\Rule;

final class ReviewFilters
{
    public static function validate(array $input): array
    {
        $rules = ['q' => ['nullable', 'string', 'min:2', 'max:100', 'not_regex:/[\p{Cc}\p{Cf}]/u'],
            'status' => ['nullable', Rule::in(['submitted'])], 'review_status' => ['nullable', Rule::enum(ReviewStatus::class)],
            'currency' => ['nullable', Rule::in(['USD', 'SYP'])], 'fulfillment' => ['nullable', 'string', Rule::in(['0', '1'])], 'reviewed' => ['nullable', 'string', Rule::in(['0', '1'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'], 'per_page' => ['sometimes', Rule::in([10, 25, 50])], 'sort' => ['sometimes', Rule::in(['newest', 'oldest'])]];
        foreach (['from', 'to', 'activity_from', 'activity_to'] as $field) {
            $rules[$field] = ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'];
        }
        if (is_string($input['q'] ?? null)) {
            $input['q'] = SearchTerm::normalize($input['q']);
        }
        $validator = validator($input, $rules);
        $validator->after(function ($validation) use ($input, $rules): void {
            if (array_diff(array_keys($input), array_keys($rules)) !== []) {
                $validation->errors()->add('filters', 'مرشحات غير مسموحة.');
            }
            foreach ([['from', 'to'], ['activity_from', 'activity_to']] as [$from, $to]) {
                if (empty($input[$from]) && empty($input[$to])) {
                    continue;
                }
                if (empty($input[$from]) || empty($input[$to])) {
                    $validation->errors()->add($from, 'اختاري تاريخي البداية والنهاية معاً.');
                } elseif (! $validation->errors()->has($from) && ! $validation->errors()->has($to)) {
                    try {
                        DashboardDateRange::from(['period' => 'custom', 'from' => $input[$from], 'to' => $input[$to]]);
                    } catch (\InvalidArgumentException) {
                        $validation->errors()->add($to, 'اختاري فترة مرتبة لا تتجاوز 93 يوماً.');
                    }
                }
            }
        });

        return array_filter($validator->validate(), fn ($value) => $value !== null && $value !== '');
    }
}
