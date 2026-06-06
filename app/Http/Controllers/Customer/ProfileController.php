<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\AppSetting;
use App\Services\CustomerVmQuotaService;
use App\Services\NationalCodeService;
use App\Services\NationalCodeVerificationClient;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly CustomerVmQuotaService $quota,
        private readonly NationalCodeService $nationalCodes,
        private readonly NationalCodeVerificationClient $nationalCodeVerification,
    ) {}

    public function show(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);

        return view('customer.profile.show', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $this->wallets->walletFor($customer),
            'wallets' => $this->wallets,
            'quota' => $this->quota->snapshot($customer),
            'invoiceCount' => $customer->invoices()->count(),
            'nationalCodeVerificationEnabled' => AppSetting::nationalCodeVerificationEnabled(),
        ]);
    }

    public function updateNationalCode(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        if ($customer->hasVerifiedNationalCode()) {
            return back()->with('status', 'کد ملی این حساب قبلا تایید شده است.');
        }

        $data = $request->validate([
            'national_code' => ['required', 'string', 'max:20'],
        ]);

        $nationalCode = $this->nationalCodes->normalize($data['national_code']);

        if (! $this->nationalCodes->isValid($nationalCode)) {
            throw ValidationException::withMessages([
                'national_code' => 'کد ملی وارد شده معتبر نیست.',
            ]);
        }

        $hash = $this->nationalCodes->hash($nationalCode);
        $exists = Customer::query()
            ->where('national_code_hash', $hash)
            ->whereKeyNot($customer->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'national_code' => 'این کد ملی قبلا برای حساب دیگری ثبت شده است.',
            ]);
        }

        if (AppSetting::nationalCodeVerificationEnabled()) {
            $rateKey = 'national-code-verification:customer:'.$customer->id;

            if (RateLimiter::tooManyAttempts($rateKey, 5)) {
                throw ValidationException::withMessages([
                    'national_code' => 'شما در هر ساعت فقط 5 بار می‌توانید استعلام کد ملی انجام دهید.',
                ]);
            }

            RateLimiter::hit($rateKey, 3600);

            try {
                $this->nationalCodeVerification->verify((string) $customer->phone, $nationalCode);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages([
                    'national_code' => $e->getMessage(),
                ]);
            }
        }

        $customer->forceFill([
            'national_code' => $nationalCode,
            'national_code_hash' => $hash,
            'national_code_verified_at' => now(),
        ])->save();

        return back()->with('status', 'کد ملی تایید شد و سطح حساب شما ارتقا پیدا کرد.');
    }
}
