<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDealRequest;
use App\Http\Requests\UpdateDealRequest;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DealController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDealRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $deal = DB::transaction(function () use ($request, $user): Deal {
                $validated = $request->validated();

                if (! array_key_exists('sort_order', $validated)) {
                    $maxSortOrder = Deal::query()
                        ->where('user_id', $user->id)
                        ->where('status', $validated['status'])
                        ->max('sort_order');

                    $validated['sort_order'] =
                        $maxSortOrder === null ? 0 : ((int) $maxSortOrder) + 1;
                }

                return $user->deals()->create($validated);
            });

            return redirect()
                ->route('deals.show', $deal)
                ->with('status', __('Deal created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'deal' => __(
                        'We could not create this deal right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateDealRequest $request,
        string $id,
    ): RedirectResponse {
        $deal = $this->resolveDeal($request, $id);

        $this->authorize('update', $deal);

        try {
            /** @var User $user */
            $user = $request->user();

            DB::transaction(function () use ($deal, $request, $user): void {
                $validated = $request->validated();
                $originalStatus = (string) $deal->status;
                $originalSortOrder = (int) $deal->sort_order;

                if (
                    array_key_exists('status', $validated) &&
                    $validated['status'] !== $originalStatus
                ) {
                    Deal::query()
                        ->where('user_id', $user->id)
                        ->where('status', $originalStatus)
                        ->where('sort_order', '>', $originalSortOrder)
                        ->decrement('sort_order');

                    $maxSortOrder = Deal::query()
                        ->where('user_id', $user->id)
                        ->where('status', $validated['status'])
                        ->max('sort_order');

                    $validated['sort_order'] =
                        $maxSortOrder === null ? 0 : ((int) $maxSortOrder) + 1;
                }

                $deal->update($validated);
            });

            return redirect()
                ->route('deals.show', $deal)
                ->with('status', __('Deal updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'deal' => __(
                        'We could not update this deal right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $deal = $this->resolveDeal($request, $id);

        $this->authorize('delete', $deal);

        try {
            DB::transaction(function () use ($deal): void {
                $userId = (int) $deal->user_id;
                $status = (string) $deal->status;
                $sortOrder = (int) $deal->sort_order;

                $deal->delete();

                Deal::query()
                    ->where('user_id', $userId)
                    ->where('status', $status)
                    ->where('sort_order', '>', $sortOrder)
                    ->decrement('sort_order');
            });

            return redirect()
                ->route('deals.index')
                ->with('status', __('Deal deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('deals.index')
                ->withErrors([
                    'deal' => __(
                        'We could not delete this deal right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the deal ensuring user-level data isolation.
     */
    private function resolveDeal(Request $request, string $id): Deal
    {
        /** @var User $user */
        $user = $request->user();

        return Deal::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
