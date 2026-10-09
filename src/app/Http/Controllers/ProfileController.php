<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Immediate deletion is not available. Students use the reviewed request on Privacy.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $student = $request->user()->isRole(UserRole::Student);

        return Redirect::route($student ? 'privacy' : 'profile.edit')->with(
            'error',
            'Accounts are not deleted from this page. Students submit a deletion request, and an administrator deactivates the login. Prediction history is kept.',
        );
    }
}
