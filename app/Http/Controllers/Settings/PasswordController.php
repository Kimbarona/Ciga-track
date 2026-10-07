<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/password');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $wasForced = $request->user()->must_change_password;

        $request->user()->update([
            'password' => $request->password,
            'must_change_password' => false,
        ]);

        // First-login password change: continue into the app.
        return $wasForced
            ? to_route('home')->with('success', 'Password set. Welcome!')
            : back()->with('success', 'Password updated.');
    }
}
