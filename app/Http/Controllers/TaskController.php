<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class TaskController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $task = $user->tasks()->create($request->validated());

            return redirect()
                ->route('tasks.show', $task)
                ->with('status', __('Task created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'task' => __(
                        'We could not create this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateTaskRequest $request,
        string $id,
    ): RedirectResponse {
        $task = $this->resolveTask($request, $id);

        $this->authorize('update', $task);

        try {
            $task->update($request->validated());

            return redirect()
                ->route('tasks.show', $task)
                ->with('status', __('Task updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'task' => __(
                        'We could not update this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $task = $this->resolveTask($request, $id);

        $this->authorize('delete', $task);

        try {
            $task->delete();

            return redirect()
                ->route('tasks.index')
                ->with('status', __('Task deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('tasks.index')
                ->withErrors([
                    'task' => __(
                        'We could not delete this task right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the task ensuring user-level data isolation.
     */
    private function resolveTask(Request $request, string $id): Task
    {
        /** @var User $user */
        $user = $request->user();

        return Task::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
