<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(string $portal): View
    {
        return view('auth.login', ['portal' => $portal]);
    }

    public function store(Request $request, string $portal): RedirectResponse
    {
        $role = $this->roleForPortal($portal);

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $loginColumn = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::guard($portal)->attempt([
            $loginColumn => $credentials['login'],
            'password' => $credentials['password'],
            'role' => $role->value,
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homePath($portal));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $portal = $request->user()?->isAdmin() ? 'admin' : 'customer';

        Auth::guard($portal)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->loginPath($portal));
    }

    private function roleForPortal(string $portal): UserRole
    {
        return $portal === 'admin' ? UserRole::Admin : UserRole::Customer;
    }

    private function homePath(string $portal): string
    {
        return '/'.config("portals.$portal.home_path");
    }

    private function loginPath(string $portal): string
    {
        return '/'.config("portals.$portal.login_path");
    }
}
