<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CustomerVerificationCodeMail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Sms\Sms0098Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class CustomerEmailVerificationController extends Controller
{
    public function create(Request $request): View
    {
        $mode = AppSetting::customerVerificationMode();

        return view('auth.verify-email-code', [
            'mode' => $mode,
            'email' => (string) $request->query('email', old('email', '')),
            'phone' => (string) $request->query('phone', old('phone', '')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $mode = AppSetting::customerVerificationMode();
        if ($mode === 'disabled') {
            return redirect()->route('customer.login')
                ->withErrors(['login' => 'تایید هویت در حال حاضر غیرفعال است.']);
        }

        $data = $request->validate(array_filter([
            'email' => $mode === 'email' ? ['required', 'email'] : null,
            'phone' => $mode === 'sms' ? ['required', 'string', 'max:30'] : null,
            'code' => ['required', 'digits:6'],
        ]));

        $customer = $mode === 'sms'
            ? Customer::query()->where('phone', $data['phone'])->first()
            : Customer::query()->where('email', $data['email'])->first();

        if (! $customer || ! $customer->email_verification_code || ! $customer->email_verification_expires_at) {
            return back()->withErrors(['code' => 'کد تایید معتبر نیست.'])->withInput();
        }

        if ($customer->email_verification_expires_at->isPast()) {
            return back()->withErrors(['code' => 'کد تایید منقضی شده است. کد جدید ارسال کنید.'])->withInput();
        }

        if (! Hash::check($data['code'], $customer->email_verification_code)) {
            return back()->withErrors(['code' => 'کد تایید نادرست است.'])->withInput();
        }

        $customer->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect('/'.trim(config('portals.customer.home_path'), '/'))
            ->with('status', 'حساب شما با موفقیت تایید شد.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $mode = AppSetting::customerVerificationMode();
        if ($mode === 'disabled') {
            return redirect()->route('customer.login')
                ->withErrors(['login' => 'تایید هویت در حال حاضر غیرفعال است.']);
        }

        $data = $request->validate(array_filter([
            'email' => $mode === 'email' ? ['required', 'email'] : null,
            'phone' => $mode === 'sms' ? ['required', 'string', 'max:30'] : null,
        ]));

        $customer = $mode === 'sms'
            ? Customer::query()->where('phone', $data['phone'])->first()
            : Customer::query()->where('email', $data['email'])->first();

        if (! $customer) {
            return back()->withErrors([
                $mode === 'sms' ? 'phone' : 'email' => $mode === 'sms'
                    ? 'حسابی با این شماره پیدا نشد.'
                    : 'حسابی با این ایمیل پیدا نشد.',
            ])->withInput();
        }

        if ($customer->email_verified_at) {
            return redirect()->route('customer.login')
                ->with('status', 'این حساب قبلا تایید شده است. وارد حساب شوید.');
        }

        try {
            self::sendVerificationCode($customer, $mode);
        } catch (Throwable $e) {
            return back()->withErrors(['verification' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'کد تایید جدید ارسال شد.');
    }

    public static function sendVerificationCode(Customer $customer, ?string $mode = null): void
    {
        $mode ??= AppSetting::customerVerificationMode();
        if ($mode === 'disabled') {
            return;
        }

        $code = (string) random_int(100000, 999999);

        $customer->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(10),
        ])->save();

        if ($mode === 'sms') {
            app(Sms0098Client::class)->sendVerificationCode((string) $customer->phone, $code);

            return;
        }

        Mail::to($customer->email)->send(new CustomerVerificationCodeMail($customer, $code));
    }
}
