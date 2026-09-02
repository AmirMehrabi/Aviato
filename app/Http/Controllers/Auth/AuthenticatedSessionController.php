<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(string $portal): View
    {
        return view('auth.login', ['portal' => $portal]);
    }

    public function store(Request $request, string $portal, AdminAuditService $audit): RedirectResponse
    {
        App::setLocale('fa');

        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $loginColumn = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $remember = $portal === 'customer' || $request->boolean('remember');

        if (! Auth::guard($portal)->attempt([
            $loginColumn => $credentials['login'],
            'password' => $credentials['password'],
        ], $remember)) {
            if ($portal === 'admin') {
                $audit->authentication($request, 'admin.login', 'failed');
            }
            throw ValidationException::withMessages([
                'login' => 'ایمیل یا شماره موبایل و رمز عبور با اطلاعات ما مطابقت ندارد.',
            ]);
        }

        $user = Auth::guard($portal)->user();

        if ($portal === 'admin' && $user instanceof User && ! $user->is_active) {
            Auth::guard($portal)->logout();
            $audit->authentication($request, 'admin.login', 'denied', $user);

            throw ValidationException::withMessages([
                'login' => 'حساب کاربری شما غیرفعال است.',
            ]);
        }

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

        if ($portal === 'admin' && $user instanceof User) {
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
            $audit->authentication($request, 'admin.login', 'success', $user);
        }

        return redirect()->intended($this->portalPath($portal, 'home_path'));
    }

    public function destroy(Request $request, AdminAuditService $audit): RedirectResponse
    {
        $routeName = (string) $request->route()?->getName();
        $portal = str_starts_with($routeName, 'customer.') ? 'customer' : 'admin';
        $wasImpersonating = $portal === 'customer'
            && $request->session()->has('impersonated_by_admin_id');

        if ($portal === 'admin' && $request->user('admin') instanceof User) {
            $audit->authentication($request, 'admin.logout', 'success', $request->user('admin'));
        }

        if ($portal === 'admin' && $request->hasSession() && Schema::hasTable('admin_session_users')) {
            DB::table('admin_session_users')->where('session_id', $request->session()->getId())->delete();
        }

        Auth::guard($portal)->logout();

        if ($wasImpersonating) {
            $request->session()->forget(['impersonated_by_admin_id', 'impersonated_customer_id']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.dashboard');
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
