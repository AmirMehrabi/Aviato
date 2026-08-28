<?php

namespace App\Http\Controllers;

use App\Services\PromotionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ElecompController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index(Request $request): View
    {
        if (! $request->session()->has('elecomp_landing_seen')) {
            $request->session()->put('elecomp_landing_seen', true);
            $this->promotions->event('elecomp_landing_view', request: $request, metadata: [
                'visitor' => hash_hmac('sha256', $request->session()->getId(), (string) config('app.key')),
            ]);
        }

        return view('elecomp');
    }

    public function claim(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $key = 'elecomp:claim:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($key, 20)) {
            return back()->withErrors(['code' => 'تعداد تلاش‌ها زیاد است. لطفاً کمی بعد دوباره تلاش کنید.']);
        }

        RateLimiter::hit($key, 3600);
        $code = $this->promotions->resolveAvailableCode($data['code']);
        $this->promotions->event('elecomp_code_accepted', $code->campaign, $code, request: $request);

        return redirect()->away(route('customer.gift-cards.landing', $code->campaign).'#'.rawurlencode($data['code']));
    }
}
