<?php

namespace App\Http\Requests;

use App\Concerns\TaskValidationRules;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    use TaskValidationRules;

    /**
     * Prepare incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->sanitizeTaskInput($this->all()));
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

        $routeTask = $this->route('task');

        $taskId =
            $routeTask instanceof Task
                ? $routeTask->id
                : (int) $routeTask;

        return $this->taskRules($this->user()->id, $taskId);
    }
}
