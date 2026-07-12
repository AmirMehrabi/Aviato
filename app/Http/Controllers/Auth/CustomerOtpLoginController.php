<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\CustomerLoginOtpCodeMail;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Services\Sms\VerificationSmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CustomerOtpLoginController extends Controller
{
    private const SESSION_CHALLENGE = 'customer_login_otp.challenge';

    private const SESSION_IDENTIFIER = 'customer_login_otp.identifier';

    private const SESSION_CHANNEL = 'customer_login_otp.channel';

    private const REQUEST_LIMIT = 3;

    private const REQUEST_DECAY = 600;

    private const VERIFY_LIMIT = 8;

    public function requestForm(): View
    {
        return view('auth.customer-login-otp-request');
    }

    public function form(Request $request): View
    {
        $challenge = $this->challengeFromSession($request);

        return view('auth.customer-login-otp', [
            'channel' => $challenge?->channel,
            'maskedDestination' => $challenge ? $this->maskDestination($challenge->identifier, $challenge->channel) : null,
            'expiresAt' => $challenge ? Carbon::parse($challenge->expires_at) : null,
            'resendAvailableAt' => $challenge ? Carbon::parse($challenge->sent_at)->addSeconds(60) : null,
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
        ]);

        $identifier = $this->normalizeIdentifier((string) $data['login']);
        $rateKey = 'customer-login-otp:request:'.sha1($identifier.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($rateKey, self::REQUEST_LIMIT)) {
            return back()->withInput()->withErrors(['login' => 'تعداد درخواست‌ها زیاد است. چند دقیقه دیگر دوباره تلاش کنید.']);
        }

        RateLimiter::hit($rateKey, self::REQUEST_DECAY);

        [$customer, $channel] = $this->findCustomerAndChannel($identifier);
        $challengeId = (string) Str::uuid();

        if (! $customer || ! $this->canUseOtp($customer)) {
            $request->session()->forget([self::SESSION_CHALLENGE, self::SESSION_IDENTIFIER, self::SESSION_CHANNEL]);

            return redirect()->route('customer.login.otp.verify')
                ->with('status', 'اگر اطلاعات حساب صحیح باشد، کد ورود برای شما ارسال شده است.');
        }

        $code = (string) random_int(100000, 999999);
        $challenge = DB::table('customer_login_otp_challenges')->insertGetId([
            'challenge' => $challengeId,
            'customer_id' => $customer->id,
            'identifier' => $identifier,
            'channel' => $channel,
            'token' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'request_ip' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->sendCodeToCustomer($customer, $channel, $code);
        } catch (Throwable $e) {
            DB::table('customer_login_otp_challenges')->where('id', $challenge)->delete();

            return back()->withInput()->withErrors(['login' => $e->getMessage()]);
        }

        $request->session()->put([
            self::SESSION_CHALLENGE => $challengeId,
            self::SESSION_IDENTIFIER => $identifier,
            self::SESSION_CHANNEL => $channel,
        ]);

        return redirect()->route('customer.login.otp.verify')
            ->with('status', $channel === 'sms'
                ? 'کد ورود به شماره موبایل شما ارسال شد.'
                : 'کد ورود به ایمیل شما ارسال شد.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $challenge = $this->challengeFromSession($request);

        if (! $challenge || ! $challenge->customer_id || $challenge->consumed_at || Carbon::parse($challenge->expires_at)->isPast()) {
            return back()->withErrors(['code' => 'کد ورود معتبر نیست یا منقضی شده است.']);
        }

        $verifyKey = 'customer-login-otp:verify:'.$challenge->challenge;
        if (RateLimiter::tooManyAttempts($verifyKey, self::VERIFY_LIMIT)) {
            return back()->withErrors(['code' => 'تعداد تلاش‌ها زیاد است. کد جدید درخواست کنید.']);
        }

        if (! Hash::check($data['code'], $challenge->token)) {
            RateLimiter::hit($verifyKey, 600);
            DB::table('customer_login_otp_challenges')->where('id', $challenge->id)->increment('attempts');

            return back()->withErrors(['code' => 'کد ورود نادرست است.']);
        }

        $customer = Customer::query()->find($challenge->customer_id);
        if (! $customer || $customer->isSuspended() || ! $this->canUseOtp($customer)) {
            return back()->withErrors(['code' => 'امکان ورود با این کد وجود ندارد.']);
        }

        DB::table('customer_login_otp_challenges')->where('id', $challenge->id)->update([
            'consumed_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $this->clearSession($request);

        return redirect()->intended('/'.trim(config('portals.customer.home_path'), '/'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $challenge = $this->challengeFromSession($request);

        if (! $challenge || ! $challenge->customer_id) {
            return redirect()->route('customer.login')->withErrors(['login' => 'ابتدا اطلاعات ورود را وارد کنید.']);
        }

        $seconds = max(0, 60 - Carbon::parse($challenge->sent_at)->diffInSeconds(now()));
        if ($seconds > 0) {
            return back()->withErrors(['code' => "برای ارسال دوباره کد، {$seconds} ثانیه صبر کنید."]);
        }

        $customer = Customer::query()->find($challenge->customer_id);
        if (! $customer || ! $this->canUseOtp($customer)) {
            return back()->withErrors(['code' => 'امکان ارسال دوباره کد وجود ندارد.']);
        }

        $code = (string) random_int(100000, 999999);
        try {
            $this->sendCodeToCustomer($customer, $challenge->channel, $code);
        } catch (Throwable $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        DB::table('customer_login_otp_challenges')->where('id', $challenge->id)->update([
            'token' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'updated_at' => now(),
        ]);
        RateLimiter::clear('customer-login-otp:verify:'.$challenge->challenge);

        return back()->with('status', 'کد جدید برای شما ارسال شد.');
    }

    private function challengeFromSession(Request $request): ?object
    {
        $challengeId = (string) $request->session()->get(self::SESSION_CHALLENGE, '');

        return $challengeId !== ''
            ? DB::table('customer_login_otp_challenges')->where('challenge', $challengeId)->first()
            : null;
    }

    private function findCustomerAndChannel(string $identifier): array
    {
        $customer = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? Customer::query()->where('email', $identifier)->first()
            : Customer::query()->where('phone', $identifier)->first();

        if (! $customer) {
            return [null, 'email'];
        }

        return filled($customer->phone)
            ? [$customer, 'sms']
            : [$customer, 'email'];
    }

    private function canUseOtp(Customer $customer): bool
    {
        return AppSetting::customerVerificationMode() === 'disabled' || filled($customer->email_verified_at);
    }

    private function sendCodeToCustomer(Customer $customer, string $channel, string $code): void
    {
        if ($channel === 'sms') {
            app(VerificationSmsSender::class)->send((string) $customer->phone, $code);

            return;
        }

        Mail::to($customer->email)->send(new CustomerLoginOtpCodeMail($customer, $code));
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : $identifier;
    }

    private function maskDestination(string $identifier, string $channel): string
    {
        if ($channel === 'sms') {
            return mb_substr($identifier, 0, 2).'******'.mb_substr($identifier, -3);
        }

        [$name, $domain] = array_pad(explode('@', $identifier, 2), 2, '');

        return mb_substr($name, 0, 1).'***@'.$domain;
    }

    private function clearSession(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_CHALLENGE,
            self::SESSION_IDENTIFIER,
            self::SESSION_CHANNEL,
        ]);
    }
}
