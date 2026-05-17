<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ContactController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            $contact = $user->contacts()->create($request->validated());

            return redirect()
                ->route('contacts.show', $contact)
                ->with('status', __('Contact created successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'contact' => __(
                        'We could not create this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateContactRequest $request,
        string $id,
    ): RedirectResponse {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('update', $contact);

        try {
            $contact->update($request->validated());

            return redirect()
                ->route('contacts.show', $contact)
                ->with('status', __('Contact updated successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'contact' => __(
                        'We could not update this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $contact = $this->resolveContact($request, $id);

        $this->authorize('delete', $contact);

        try {
            $contact->delete();

            return redirect()
                ->route('contacts.index')
                ->with('status', __('Contact deleted successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('contacts.index')
                ->withErrors([
                    'contact' => __(
                        'We could not delete this contact right now. Please try again.',
                    ),
                ]);
        }
    }

    /**
     * Resolve the contact ensuring user-level data isolation.
     */
    private function resolveContact(Request $request, string $id): Contact
    {
        /** @var User $user */
        $user = $request->user();

        return Contact::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();
    }
}
