<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PromotionCampaign;
use App\Models\PromotionEvent;
use App\Models\PromotionException;
use App\Services\PromotionService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions, private readonly WalletService $wallets) {}

    public function index(Request $request): View
    {
        $campaigns = PromotionCampaign::query()->withCount(['codes', 'redemptions'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.promotions.index', compact('campaigns') + ['wallets' => $this->wallets]);
    }

    public function create(): View
    {
        return view('admin.promotions.create', ['defaultExpiry' => now()->addDays(config('promotions.default_expiry_days'))->format('Y-m-d\TH:i')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in([PromotionCampaign::TYPE_CREDIT, PromotionCampaign::TYPE_PERCENTAGE])],
            'audience' => ['required', Rule::in([PromotionCampaign::AUDIENCE_ALL, PromotionCampaign::AUDIENCE_NEW, PromotionCampaign::AUDIENCE_ALLOWLIST])],
            'code_count' => ['required', 'integer', 'min:1', 'max:'.config('promotions.max_batch_size')],
            'credit_amount_toman' => ['nullable', 'integer', 'min:1'],
            'percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'minimum_top_up_toman' => ['nullable', 'integer', 'min:1', 'max:50000000'],
            'maximum_bonus_toman' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'], 'expires_at' => ['required', 'date', 'after:now'],
            'headline' => ['nullable', 'string', 'max:150'], 'message' => ['nullable', 'string', 'max:1000'], 'terms' => ['nullable', 'string', 'max:2000'],
        ]);

        $credit = isset($data['credit_amount_toman']) ? (int) $data['credit_amount_toman'] * 10 : null;
        $minimum = isset($data['minimum_top_up_toman']) ? (int) $data['minimum_top_up_toman'] * 10 : null;
        $bonus = isset($data['maximum_bonus_toman']) ? (int) $data['maximum_bonus_toman'] * 10 : null;
        if ($data['type'] === PromotionCampaign::TYPE_CREDIT && (! $credit || $credit > config('promotions.max_credit_amount'))) {
            return back()->withErrors(['credit_amount_toman' => 'مبلغ اعتبار خارج از سقف مجاز است.'])->withInput();
        }
        if ($data['type'] === PromotionCampaign::TYPE_PERCENTAGE && (! ($data['percentage'] ?? null) || ! $minimum || ! $bonus || $bonus > config('promotions.max_bonus_amount'))) {
            return back()->withErrors(['maximum_bonus_toman' => 'قواعد پاداش درصدی کامل یا در محدوده مجاز نیست.'])->withInput();
        }
        $liability = (int) $data['code_count'] * (int) ($data['type'] === PromotionCampaign::TYPE_CREDIT ? $credit : $bonus);
        if ($liability > config('promotions.max_campaign_liability')) {
            return back()->withErrors(['code_count' => 'تعهد مالی کمپین از سقف سامانه بیشتر است.'])->withInput();
        }

        $campaign = PromotionCampaign::create([
            'name' => $data['name'], 'type' => $data['type'], 'audience' => $data['audience'], 'status' => 'draft', 'currency' => 'IRR',
            'credit_amount' => $data['type'] === PromotionCampaign::TYPE_CREDIT ? $credit : null,
            'percentage' => $data['type'] === PromotionCampaign::TYPE_PERCENTAGE ? $data['percentage'] : null,
            'minimum_top_up' => $data['type'] === PromotionCampaign::TYPE_PERCENTAGE ? $minimum : null,
            'maximum_bonus' => $data['type'] === PromotionCampaign::TYPE_PERCENTAGE ? $bonus : null,
            'code_count' => $data['code_count'], 'maximum_liability' => $liability,
            'headline' => $data['headline'] ?? null, 'message' => $data['message'] ?? null, 'terms' => $data['terms'] ?? null,
            'starts_at' => $data['starts_at'] ?? null, 'expires_at' => Carbon::parse($data['expires_at']), 'created_by_id' => $request->user('admin')->id,
        ]);
        $this->promotions->event('campaign_created', $campaign, user: $request->user('admin'), request: $request, metadata: ['maximum_liability' => $liability]);

        return redirect()->route('admin.promotions.show', $campaign)->with('status', 'کمپین پیش‌نویس ایجاد شد.');
    }

    public function show(Request $request, PromotionCampaign $campaign): View
    {
        $campaign->loadCount(['codes', 'redemptions'])->load(['createdBy']);
        $codes = $campaign->codes()->latest()->paginate(50);
        $eventCounts = PromotionEvent::query()->where('promotion_campaign_id', $campaign->id)
            ->whereIn('action', ['elecomp_code_accepted', 'elecomp_gift_landing_view', 'elecomp_auth_started', 'elecomp_server_created'])
            ->selectRaw('action, COUNT(*) as aggregate')->groupBy('action')->pluck('aggregate', 'action');
        $funnel = [
            ['کد معتبر', (int) ($eventCounts['elecomp_code_accepted'] ?? 0)],
            ['مشاهده هدیه', (int) ($eventCounts['elecomp_gift_landing_view'] ?? 0)],
            ['شروع ورود/ثبت‌نام', (int) ($eventCounts['elecomp_auth_started'] ?? 0)],
            ['استفاده از هدیه', (int) $campaign->redemptions_count],
            ['ساخت سرور', (int) ($eventCounts['elecomp_server_created'] ?? 0)],
        ];
        $this->promotions->event('codes_viewed', $campaign, user: $request->user('admin'), request: $request, metadata: ['page' => $codes->currentPage()]);

        return view('admin.promotions.show', compact('campaign', 'codes', 'funnel') + ['wallets' => $this->wallets]);
    }

    public function updateCopy(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(['headline' => ['nullable', 'string', 'max:150'], 'message' => ['nullable', 'string', 'max:1000'], 'terms' => ['nullable', 'string', 'max:2000']]);
        $campaign->forceFill($data + ['updated_by_id' => $request->user('admin')->id])->save();
        $this->promotions->event('display_copy_updated', $campaign, user: $request->user('admin'), request: $request);

        return back()->with('status', 'متن کارت‌ها به‌روزرسانی شد.');
    }

    public function generate(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        $this->promotions->generateCodes($campaign, $request->user('admin'), $request);

        return back()->with('status', 'کدها تولید و قواعد مالی قفل شدند.');
    }

    public function activate(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->rules_locked_at && $campaign->codes()->count() === $campaign->code_count, 422);
        $status = $campaign->starts_at?->isFuture() ? 'scheduled' : 'active';
        $campaign->forceFill(['status' => $status, 'updated_by_id' => $request->user('admin')->id])->save();
        $this->promotions->event('campaign_activated', $campaign, user: $request->user('admin'), request: $request, metadata: ['status' => $status]);

        return back()->with('status', 'کمپین فعال شد.');
    }

    public function pause(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        $campaign->forceFill(['status' => 'paused', 'updated_by_id' => $request->user('admin')->id])->save();
        $this->promotions->event('campaign_paused', $campaign, user: $request->user('admin'), request: $request);

        return back()->with('status', 'کمپین متوقف شد؛ رزروهای جاری تا پایان مهلت معتبرند.');
    }

    public function resume(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        abort_if($campaign->expires_at->isPast(), 422);
        $campaign->forceFill(['status' => $campaign->starts_at?->isFuture() ? 'scheduled' : 'active', 'updated_by_id' => $request->user('admin')->id])->save();
        $this->promotions->event('campaign_resumed', $campaign, user: $request->user('admin'), request: $request);

        return back()->with('status', 'کمپین از سر گرفته شد.');
    }

    public function revokeUnused(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        $count = $campaign->codes()->where('status', 'available')->update(['status' => 'revoked', 'revoked_at' => now(), 'updated_at' => now()]);
        $campaign->forceFill(['status' => 'ended', 'updated_by_id' => $request->user('admin')->id])->save();
        $this->promotions->event('unused_codes_revoked', $campaign, user: $request->user('admin'), request: $request, metadata: ['count' => $count]);

        return back()->with('status', "$count کد استفاده‌نشده باطل شد.");
    }

    public function allowlist(Request $request, PromotionCampaign $campaign): RedirectResponse
    {
        abort_if($campaign->rules_locked_at, 422);
        abort_unless($campaign->audience === PromotionCampaign::AUDIENCE_ALLOWLIST, 422);
        $request->validate(['identifiers' => ['nullable', 'string'], 'csv' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120']]);
        $identifiers = preg_split('/[\r\n,]+/', (string) $request->input('identifiers'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($request->hasFile('csv')) {
            $handle = fopen($request->file('csv')->getRealPath(), 'r');
            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[0])) {
                    $identifiers[] = trim((string) $row[0]);
                }
            }
            fclose($handle);
        }
        $ids = [];
        foreach (array_slice(array_unique(array_filter(array_map('trim', $identifiers))), 0, 25000) as $identifier) {
            $customer = Customer::query()->where('id', ctype_digit($identifier) ? (int) $identifier : -1)->orWhere('email', $identifier)->orWhere('phone', $identifier)->first();
            if ($customer) {
                $ids[] = $customer->id;
            }
        }
        $campaign->allowedCustomers()->syncWithoutDetaching(array_unique($ids));
        $this->promotions->event('allowlist_imported', $campaign, user: $request->user('admin'), request: $request, metadata: ['matched' => count(array_unique($ids)), 'submitted' => count($identifiers)]);

        return back()->with('status', count(array_unique($ids)).' مشتری به فهرست مجاز افزوده شد.');
    }

    public function print(Request $request, PromotionCampaign $campaign): View
    {
        $codes = $campaign->codes()->whereNotIn('status', ['revoked'])->paginate(100);
        $this->promotions->event('codes_printed', $campaign, user: $request->user('admin'), request: $request, metadata: ['page' => $codes->currentPage(), 'count' => $codes->count()]);

        return view('admin.promotions.print', compact('campaign', 'codes') + ['wallets' => $this->wallets]);
    }

    public function exceptions(PromotionCampaign $campaign): View
    {
        return view('admin.promotions.exceptions', ['campaign' => $campaign, 'exceptions' => PromotionException::query()->where('promotion_campaign_id', $campaign->id)->with('payment')->latest()->paginate(30), 'wallets' => $this->wallets]);
    }

    public function resolveException(Request $request, PromotionException $exception): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['grant', 'deny'])], 'resolution_note' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $exception, $request): void {
            $exception = PromotionException::query()->lockForUpdate()->findOrFail($exception->id);
            abort_unless($exception->status === 'open', 422);
            if ($data['decision'] === 'grant') {
                $payment = $exception->payment;
                $this->wallets->credit($payment->customer, $exception->expected_bonus, 'جبران پاداش پروموشن پرداخت دیرهنگام', $request->user('admin'), $exception, ['category' => 'promotion_exception_credit', 'payment_id' => $payment->id]);
            }
            $exception->forceFill(['status' => $data['decision'] === 'grant' ? 'granted' : 'denied', 'resolution_note' => $data['resolution_note'], 'resolved_by_id' => $request->user('admin')->id, 'resolved_at' => now()])->save();
            $this->promotions->event('exception_resolved', $exception->campaign, $exception->code, $request->user('admin'), request: $request, metadata: ['decision' => $data['decision'], 'exception_id' => $exception->id]);
        });

        return back()->with('status', 'استثنا بررسی و ثبت شد.');
    }
}
