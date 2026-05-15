<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CompanyController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $company = $user->companies()->create($request->validated());

            return redirect()
                ->route('companies.show', $company)
                ->with('status', __('Company created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'company' => __(
                        'We could not create this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateCompanyRequest $request,
        string $id,
    ): RedirectResponse {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('update', $company);

        try {
            $company->update($request->validated());

            return redirect()
                ->route('companies.show', $company)
                ->with('status', __('Company updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'company' => __(
                        'We could not update this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->resolveCompany($request, $id);

        $this->authorize('delete', $company);

        try {
            $company->delete();

            return redirect()
                ->route('companies.index')
                ->with('status', __('Company deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('companies.index')
                ->withErrors([
                    'company' => __(
                        'We could not delete this company right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the company ensuring user-level data isolation.
     */
    private function resolveCompany(Request $request, string $id): Company
    {
        /** @var User $user */
        $user = $request->user();

        return Company::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
