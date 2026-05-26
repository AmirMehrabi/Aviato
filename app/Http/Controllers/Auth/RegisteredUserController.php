<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(string $portal): View
    {
        return view('auth.register', [
            'portal' => $portal,
            'verificationMode' => $portal === 'customer' ? AppSetting::customerVerificationMode() : 'disabled',
        ]);
    }

    public function store(Request $request, string $portal): RedirectResponse
    {
        $model = $portal === 'admin' ? User::class : Customer::class;
        $verificationMode = $portal === 'customer' ? AppSetting::customerVerificationMode() : 'disabled';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $portal === 'customer' && $verificationMode === 'email'
                ? ['required', 'email', 'max:255', Rule::unique($model, 'email')]
                : ['nullable', 'required_without:phone', 'email', 'max:255', Rule::unique($model, 'email')],
            'phone' => $portal === 'customer' && $verificationMode === 'sms'
                ? ['required', 'string', 'max:30', 'regex:/^(\+98|98|0)?9\d{9}$/', Rule::unique($model, 'phone')]
                : ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,29}$/', Rule::unique($model, 'phone')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var Model $account */
        $account = $model::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        if ($portal === 'customer' && $account instanceof Customer && $verificationMode !== 'disabled') {
            try {
                CustomerEmailVerificationController::sendVerificationCode($account, $verificationMode);
            } catch (Throwable $e) {
                $account->delete();

                return back()
                    ->withInput($request->except('password', 'password_confirmation'))
                    ->withErrors(['verification' => $e->getMessage()]);
            }

            $routeParams = $verificationMode === 'sms'
                ? ['phone' => $account->phone]
                : ['email' => $account->email];

            return redirect()
                ->route('customer.verification.notice', $routeParams)
                ->with('status', $verificationMode === 'sms' ? 'کد تایید پیامک ارسال شد.' : 'کد تایید برای ایمیل شما ارسال شد.');
        }

        Auth::guard($portal)->login($account);
        $request->session()->regenerate();

        return redirect('/'.trim(config("portals.$portal.home_path"), '/'));
    }
}
