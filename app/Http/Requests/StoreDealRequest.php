<?php

namespace App\Http\Requests;

use App\Concerns\DealValidationRules;
use App\Models\Deal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDealRequest extends FormRequest
{
    use DealValidationRules;

    /**
     * Prepare incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizeDealInput($this->all()));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Deal::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->user() === null) {
            return [];
        }

        return $this->dealRules($this->user()->id);
    }
}
