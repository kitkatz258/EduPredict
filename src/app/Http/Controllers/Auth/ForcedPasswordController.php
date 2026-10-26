<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Consent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcedPasswordController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();

        return view('auth.forced-password', [
            'needsConsent' => $user?->isRole(UserRole::Student) && $user->consented_at === null,
            'consentVersion' => config('edupredict.consent.current_version'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $needsConsent = $user->isRole(UserRole::Student) && $user->consented_at === null;
        $rules = [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
        if ($needsConsent) {
            $rules['consent'] = ['accepted'];
        }

        $validated = $request->validate($rules, [
            'consent.accepted' => 'You must accept the informed consent notice to continue.',
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        if ($needsConsent) {
            $user->consented_at = now();
            Consent::query()->create([
                'user_id' => $user->id,
                'version' => config('edupredict.consent.current_version'),
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
            ]);
        }

        $user->save();

        return redirect()->route('dashboard')->with('success', 'Password updated.');
    }
}
