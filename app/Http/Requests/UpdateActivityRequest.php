<?php

namespace App\Http\Requests;

use App\Concerns\ActivityValidationRules;
use App\Models\Activity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $routeActivity = $this->route('activity');

        $activityId =
            $routeActivity instanceof Activity
                ? $routeActivity->id
                : (int) $routeActivity;

        if ($activityId < 1) {
            return false;
        }

        $activity = Activity::query()
            ->select(['id', 'user_id'])
            ->whereKey($activityId)
            ->first();

        return $activity !== null && $user->can('update', $activity);
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
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
