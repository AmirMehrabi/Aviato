<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PromotionCampaign;
use App\Services\ProjectAccessService;
use App\Services\PromotionService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class GiftCardController extends Controller
{
    public function __construct(private readonly PromotionService $promotions, private readonly ProjectAccessService $projects) {}

    public function landing(Request $request, PromotionCampaign $campaign): View
    {
        $request->session()->put('url.intended', route('customer.wallet.show', ['gift_card' => 1], false));

        return view('customer.gift-cards.landing', compact('campaign'));
    }

    public function redeem(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewBilling($project, $customer), 404);
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);

        $customerKey = 'promotion:redeem:customer:'.$customer->id;
        $ipKey = 'promotion:redeem:ip:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($customerKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return back()->withErrors(['code' => 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً بعداً دوباره تلاش کنید.']);
        }
        RateLimiter::hit($customerKey, 600);
        RateLimiter::hit($ipKey, 3600);

        $redemption = $this->promotions->redeemCredit($data['code'], $customer, $project, $request);

        return redirect()->route('customer.wallet.show')->with('status', 'کارت هدیه با موفقیت اعمال شد و '.app(WalletService::class)->format($redemption->benefit_amount).' به کیف پول افزوده شد.');
    }
}
