<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
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

        $verificationMode = $portal === 'customer' ? AppSetting::customerVerificationMode() : 'disabled';
        if ($portal === 'customer' && $verificationMode !== 'disabled' && $user instanceof Customer && ! $user->email_verified_at) {
            Auth::guard($portal)->logout();

            throw ValidationException::withMessages([
                'login' => $verificationMode === 'sms'
                    ? 'شماره موبایل حساب شما هنوز تایید نشده است. کد پیامک را وارد کنید.'
                    : 'ایمیل حساب شما هنوز تایید نشده است. کد تایید را وارد کنید.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->portalPath($portal, 'home_path'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $routeName = (string) $request->route()?->getName();
        $portal = str_starts_with($routeName, 'customer.') ? 'customer' : 'admin';

        Auth::guard($portal)->logout();

        if ($portal === 'customer' && Auth::guard('admin')->check()) {
            $request->session()->forget(['impersonated_by_admin_id', 'impersonated_customer_id']);
            $request->session()->regenerateToken();

            return redirect($this->portalPath('admin', 'home_path'));
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->portalPath($portal, 'login_path'));
    }

    private function portalPath(string $portal, string $key): string
    {
        return '/'.trim(config("portals.$portal.$key"), '/');
    }
}
