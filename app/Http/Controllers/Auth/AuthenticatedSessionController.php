<?php

namespace App\Http\Controllers\Auth;

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
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $loginColumn = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::guard($portal)->attempt([
            $loginColumn => $credentials['login'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->homeUrl($portal));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $portal = Auth::guard('admin')->check() ? 'admin' : 'customer';

        Auth::guard($portal)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->loginUrl($portal));
    }

    private function homeUrl(string $portal): string
    {
        return $this->portalUrl($portal, config("portals.$portal.home_path"));
    }

    private function loginUrl(string $portal): string
    {
        return $this->portalUrl($portal, config("portals.$portal.login_path"));
    }

    private function portalUrl(string $portal, string $path): string
    {
        $domain = config("portals.$portal.domain");
        $path = '/'.trim($path, '/');

        return $domain ? request()->getScheme().'://'.$domain.$path : $path;
    }
}
