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
        $this->promotions->event('elecomp_gift_landing_view', $campaign, request: $request);

        return view('customer.gift-cards.landing', compact('campaign'));
    }

    public function continue(Request $request, PromotionCampaign $campaign, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['login', 'register'], true), 404);
        $request->session()->put('url.intended', route('customer.wallet.show', ['gift_card' => 1], false));
        $this->promotions->event('elecomp_auth_started', $campaign, customer: $request->user('customer'), request: $request, metadata: ['action' => $action]);

        return redirect()->route($action === 'register' ? 'customer.register' : 'customer.login');
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

        $request->session()->put('elecomp_attribution', ['redemption_id' => $redemption->id, 'expires_at' => now()->addDays(7)->timestamp]);

        return redirect()->route('customer.wallet.show')->with([
            'status' => 'کارت هدیه با موفقیت اعمال شد و '.app(WalletService::class)->format($redemption->benefit_amount).' به کیف پول افزوده شد.',
            'promotion_success' => true,
        ]);
    }
}
