<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CustomerPasswordResetCodeMail;
use App\Models\Customer;
use App\Services\Sms\VerificationSmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class CustomerPasswordResetController extends Controller
{
    private const SESSION_IDENTIFIER = 'customer_password_reset.identifier';

    private const SESSION_CHANNEL = 'customer_password_reset.channel';

    public function requestForm(): View
    {
        return view('auth.customer-password-forgot');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        [$customer, $channel, $identifier] = $this->findCustomer((string) $data['login']);

        if (! $customer) {
            return back()
                ->withInput()
                ->withErrors(['login' => 'حساب مشتری با این ایمیل یا شماره موبایل پیدا نشد.']);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('customer_password_reset_tokens')->updateOrInsert(
            ['email' => $identifier],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ],
        );

        try {
            $this->sendResetCode($customer, $channel, $code);
        } catch (Throwable $e) {
            DB::table('customer_password_reset_tokens')->where('email', $identifier)->delete();

            return back()
                ->withInput()
                ->withErrors(['login' => $e->getMessage()]);
        }

        return redirect()
            ->route('customer.password.otp', ['login' => $identifier, 'channel' => $channel])
            ->with('status', $channel === 'sms' ? 'کد OTP بازیابی رمز با پیامک ارسال شد.' : 'کد OTP بازیابی رمز به ایمیل شما ارسال شد.');
    }

    public function otpForm(Request $request): View
    {
        return view('auth.customer-password-otp', [
            'login' => (string) $request->query('login', old('login', '')),
            'channel' => (string) $request->query('channel', old('channel', 'email')),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'in:email,sms'],
            'code' => ['required', 'digits:6'],
        ]);

        $record = DB::table('customer_password_reset_tokens')->where('email', $data['login'])->first();

        if (! $record || ! $record->created_at || Carbon::parse($record->created_at)->addMinutes(10)->isPast()) {
            return back()->withErrors(['code' => 'کد OTP معتبر نیست یا منقضی شده است.'])->withInput();
        }

        if (! Hash::check($data['code'], $record->token)) {
            return back()->withErrors(['code' => 'کد OTP نادرست است.'])->withInput();
        }

        $request->session()->put(self::SESSION_IDENTIFIER, $data['login']);
        $request->session()->put(self::SESSION_CHANNEL, $data['channel']);

        return redirect()->route('customer.password.reset')
            ->with('status', 'کد OTP تایید شد. رمز عبور جدید را وارد کنید.');
    }

    public function resetForm(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has(self::SESSION_IDENTIFIER)) {
            return redirect()->route('customer.password.request')
                ->withErrors(['login' => 'ابتدا کد OTP بازیابی رمز را دریافت و تایید کنید.']);
        }

        return view('auth.customer-password-reset');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $identifier = (string) $request->session()->get(self::SESSION_IDENTIFIER, '');

        if ($identifier === '') {
            return redirect()->route('customer.password.request')
                ->withErrors(['login' => 'ابتدا کد OTP بازیابی رمز را دریافت و تایید کنید.']);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        [$customer] = $this->findCustomer($identifier);

        if (! $customer) {
            $this->clearResetSession($request);

            return redirect()->route('customer.password.request')
                ->withErrors(['login' => 'حساب مشتری پیدا نشد.']);
        }

        $customer->forceFill([
            'password' => $data['password'],
        ])->save();

        DB::table('customer_password_reset_tokens')->where('email', $identifier)->delete();
        $this->clearResetSession($request);

        return redirect()->route('customer.login')
            ->with('status', 'رمز عبور شما تغییر کرد. حالا وارد حساب مشتری شوید.');
    }

    /**
     * @return array{0: ?Customer, 1: string, 2: string}
     */
    private function findCustomer(string $login): array
    {
        $login = trim($login);
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        if ($isEmail) {
            return [
                Customer::query()->where('email', $login)->first(),
                'email',
                $login,
            ];
        }

        return [
            Customer::query()->where('phone', $login)->first(),
            'sms',
            $login,
        ];
    }

    private function sendResetCode(Customer $customer, string $channel, string $code): void
    {
        if ($channel === 'sms') {
            app(VerificationSmsSender::class)->send((string) $customer->phone, $code);

            return;
        }

        Mail::to($customer->email)->send(new CustomerPasswordResetCodeMail($customer, $code));
    }

    private function clearResetSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_IDENTIFIER,
            self::SESSION_CHANNEL,
        ]);
    }
}
