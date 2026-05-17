<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ActivityController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActivityRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $activity = $user->activities()->create($request->validated());

            return redirect()
                ->route('activities.show', $activity)
                ->with('status', __('Activity created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'activity' => __(
                        'We could not create this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateActivityRequest $request,
        string $id,
    ): RedirectResponse {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('update', $activity);

        try {
            $activity->update($request->validated());

            return redirect()
                ->route('activities.show', $activity)
                ->with('status', __('Activity updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'activity' => __(
                        'We could not update this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $activity = $this->resolveActivity($request, $id);

        $this->authorize('delete', $activity);

        try {
            $activity->delete();

            return redirect()
                ->route('activities.index')
                ->with('status', __('Activity deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('activities.index')
                ->withErrors([
                    'activity' => __(
                        'We could not delete this activity right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the activity ensuring user-level data isolation.
     */
    private function resolveActivity(Request $request, string $id): Activity
    {
        /** @var User $user */
        $user = $request->user();

        return Activity::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
