<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'cnpj' => ['required', 'string', 'regex:/^(\d{14}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/', Rule::unique('suppliers', 'cnpj')->where('organization_id', $this->user()->organization_id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            /** @ignoreParam */
            'organization_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cnpj')) {
            $this->merge(['cnpj' => preg_replace('/\D/', '', (string) $this->input('cnpj'))]);
        }
    }
}