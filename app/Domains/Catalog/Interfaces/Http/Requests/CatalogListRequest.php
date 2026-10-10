<?php

namespace App\Domains\Catalog\Interfaces\Http\Requests;

use App\Domains\Catalog\Application\DTOs\CatalogFilters;
use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (in_array($this->query('recommended'), ['true', 'false'], true)) {
            $this->merge(['recommended' => $this->query('recommended') === 'true']);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'required', 'string', 'max:200'],
            'brand' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u'],
            'concern' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[\pL\pN]+(?:-[\pL\pN]+)*$/u'],
            'availability' => ['sometimes', 'required', Rule::enum(AvailabilityStatus::class)],
            'recommended' => ['sometimes', 'required', 'boolean'],
            'page' => ['sometimes', 'required', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'required', 'integer', 'min:1', 'max:48'],
            'sort' => ['sometimes', 'required', Rule::in(['newest', 'name_asc', 'name_desc'])],
        ];
    }

    public function filters(): CatalogFilters
    {
        $data = $this->validated();

        return new CatalogFilters($data['q'] ?? null, $data['brand'] ?? null, $data['concern'] ?? null,
            $data['availability'] ?? null, isset($data['recommended']) ? (bool) $data['recommended'] : null,
            (int) ($data['per_page'] ?? 12), $data['sort'] ?? 'newest');
    }
}
