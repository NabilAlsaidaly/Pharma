<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Requests;

use App\Domains\ManagementIdentity\Application\OwnerCredentials;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class OwnerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => OwnerCredentials::email($this->input('email'))]);
        }
        if (is_string($this->input('display_name'))) {
            $this->merge(['display_name' => OwnerCredentials::name($this->input('display_name'))]);
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getName()) {
            'owner.login.submit' => ['email' => ['required', 'string', 'email:rfc', 'max:254'], 'password' => ['required', 'string', 'max:72', function ($attribute, $value, $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('بيانات الدخول غير صالحة.');
                }
            }]],
            'owner.profile.update' => ['display_name' => OwnerCredentials::nameRules()],
            'owner.password.update' => ['current_password' => ['required', 'string', 'max:72', function ($attribute, $value, $fail): void {
                if (is_string($value) && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('بيانات الدخول غير صالحة.');
                }
            }],
                'password' => [...OwnerCredentials::passwordRules(), 'confirmed'], 'password_confirmation' => ['required', 'string', 'max:72']],
            default => [],
        };
    }

    public function messages(): array
    {
        return ['required' => 'هذا الحقل مطلوب.', 'string' => 'القيمة غير صالحة.', 'email' => 'يرجى إدخال بريد إلكتروني صالح.', 'max' => 'القيمة تتجاوز الحد المسموح.',
            'min' => 'كلمة المرور يجب أن تحتوي 12 محرفاً على الأقل.', 'confirmed' => 'تأكيد كلمة المرور غير مطابق.', 'regex' => 'النص غير صالح.', 'not_regex' => 'محارف النص غير مسموحة.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = [...array_keys($this->rules()), '_token', '_method'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $field) {
                $validator->errors()->add($field, 'هذا الحقل غير مسموح.');
            }
            foreach (array_keys($this->query()) as $field) {
                $validator->errors()->add($field, 'معاملات الرابط غير مسموحة.');
            }
        }];
    }
}
