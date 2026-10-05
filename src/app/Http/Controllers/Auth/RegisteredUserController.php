<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StudentRegistrationRequest;
use App\Models\Program;
use App\Services\Auth\StudentRegistrationService;
use App\Support\RoleHome;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'programs' => Program::query()->orderBy('name')->get(),
            'consentVersion' => config('edupredict.consent.current_version'),
        ]);
    }

    public function store(StudentRegistrationRequest $request, StudentRegistrationService $registration): RedirectResponse
    {
        $user = $registration->register([
            ...$request->safe()->except(['consent', 'password_confirmation']),
            'ip' => $request->ip(),
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->to(RoleHome::url($user));
    }
}
