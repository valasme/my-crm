<?php

namespace App\Http\Requests;

use App\Concerns\ActivityValidationRules;
use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityRequest extends FormRequest
{
    use ActivityValidationRules;

    /**
     * Prepare incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizeActivityInput($this->all()));
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

        $routeActivity = $this->route('activity');

        $activityId =
            $routeActivity instanceof Activity
                ? $routeActivity->id
                : (int) $routeActivity;

        return $this->activityRules($this->user()->id, $activityId);
    }
}
