<?php

namespace App\Http\Requests;

use App\Concerns\CompanyValidationRules;
use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    use CompanyValidationRules;

    /**
     * Prepare incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizeCompanyInput($this->all()));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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

        $routeCompany = $this->route('company');

        $companyId =
            $routeCompany instanceof Company
                ? $routeCompany->id
                : (int) $routeCompany;

        return $this->companyRules($this->user()->id, $companyId);
    }
}
