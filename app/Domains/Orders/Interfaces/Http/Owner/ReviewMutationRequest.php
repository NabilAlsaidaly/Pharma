<?php

namespace App\Domains\Orders\Interfaces\Http\Owner;

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Domain\Review\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReviewMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = Auth::guard('owner')->user();

        return $owner instanceof ManagementUser && $owner->role === 'owner' && $owner->status === 'active';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('operation_id'))) {
            $this->merge(['operation_id' => strtolower($this->input('operation_id'))]);
        }
    }

    public function rules(): array
    {
        return ['operation_id' => ['required', 'uuid'], 'expected_version' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,19})$/D'],
            'kind' => ['required', Rule::in(['transition', 'note', 'follow_up'])],
            'target' => ['required_if:kind,transition', 'prohibited_unless:kind,transition', Rule::enum(ReviewStatus::class)],
            'note' => ['nullable', 'required_unless:kind,transition', 'string', 'max:2000', 'regex:/\S/u', 'not_regex:/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f\p{Cf}]/u']];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if (array_diff(array_keys($this->all()), [...array_keys($this->rules()), '_token']) !== []) {
                $validator->errors()->add('fields', 'حقول غير مسموحة.');
            }
        }];
    }
}
