<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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

        $user = Auth::guard($portal)->user();

        if ($portal === 'customer' && $user instanceof Customer && $user->isSuspended()) {
            Auth::guard($portal)->logout();

            throw ValidationException::withMessages([
                'login' => 'حساب شما تعلیق شده است. لطفا با پشتیبانی تماس بگیرید.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->portalPath($portal, 'home_path'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $portal = Auth::guard('admin')->check() ? 'admin' : 'customer';

        Auth::guard($portal)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->portalPath($portal, 'login_path'));
    }

    private function portalPath(string $portal, string $key): string
    {
        return '/'.trim(config("portals.$portal.$key"), '/');
    }
}
