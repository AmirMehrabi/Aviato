<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NetworkIngestionCheckpoint;
use App\Models\NetworkUsageBucket;
use App\Models\NetworkUsageRating;
use App\Models\VirtualMachine;
use App\Models\VmNetworkBillingPeriod;
use App\Services\IpdrClient;
use App\Services\NetworkUsageRatingService;
use App\Services\NetworkUsageReconciliationService;
use App\Services\NetworkUsageReportService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Throwable;

class NetworkBillingController extends Controller
{
    public function __construct(private readonly NetworkUsageReportService $reports, private readonly WalletService $wallets) {}

    public function index(Request $request): View
    {
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'rated', 'ignored', 'quarantined'])]])['status'] ?? null;
        $periods = VmNetworkBillingPeriod::query()->with('virtualMachine.customer')->latest('period_start')->paginate(15)->withQueryString();
        $checkpoint = NetworkIngestionCheckpoint::query()->where('source', config('services.ipdr.source', 'ipdr'))->first();

        return view('admin.billing.network.index', [
            'checkpoint' => $checkpoint, 'periods' => $periods, 'reports' => $this->reports, 'wallets' => $this->wallets,
            'statusFilter' => $status,
            'stats' => [
                'month_bytes' => (int) VmNetworkBillingPeriod::query()->where('period_end', '>', now())->sum('rated_bytes'),
                'month_amount' => (int) VmNetworkBillingPeriod::query()->where('period_end', '>', now())->sum('accrued_amount'),
                'rated' => NetworkUsageBucket::query()->where('processing_status', 'rated')->count(),
                'exceptions' => NetworkUsageBucket::query()->whereIn('processing_status', ['quarantined'])->orWhereIn('completeness', ['partial', 'missing'])->count(),
                'latest' => NetworkUsageBucket::query()->max('interval_end'),
            ],
            'recentBuckets' => NetworkUsageBucket::query()->with('virtualMachine')->when($status, fn ($q) => $q->where('processing_status', $status))->latest('interval_end')->limit(12)->get(),
        ]);
    }

    public function ipdr(): View
    {
        return view('admin.billing.network.ipdr', [
            'checkpoint' => NetworkIngestionCheckpoint::query()->where('source', config('services.ipdr.source', 'ipdr'))->first(),
            'configured' => filled(config('services.ipdr.url')) && filled(config('services.ipdr.token')),
            'baseUrl' => config('services.ipdr.url'), 'source' => config('services.ipdr.source', 'ipdr'),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after:from']]);
        $parameters = array_filter(['--from' => $data['from'] ?? null, '--to' => $data['to'] ?? null]);
        Artisan::queue('network-usage:sync', $parameters);

        return back()->with('status', 'همگام‌سازی IPDR در صف قرار گرفت.');
    }

    public function testConnection(IpdrClient $ipdr): RedirectResponse
    {
        try {
            $to = CarbonImmutable::now('UTC');
            $ipdr->summaries($to->subHour()->toIso8601String(), $to->toIso8601String());

            return back()->with('status', 'اتصال و احراز هویت IPDR با موفقیت آزمایش شد.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'آزمایش اتصال IPDR ناموفق بود: '.$exception->getMessage());
        }
    }

    public function exceptions(Request $request): View
    {
        $buckets = NetworkUsageBucket::query()->with('virtualMachine')
            ->where(fn ($q) => $q->where('processing_status', 'quarantined')->orWhereIn('completeness', ['partial', 'missing']))
            ->latest('source_updated_at')->paginate(25)->withQueryString();

        return view('admin.billing.network.exceptions', compact('buckets'));
    }

    public function retry(NetworkUsageBucket $bucket, NetworkUsageRatingService $rating): RedirectResponse
    {
        if (! $bucket->virtual_machine_id) {
            return back()->with('error', 'تا زمانی که VM UUID شناخته نشود امکان تلاش دوباره وجود ندارد.');
        }

        $bucket->forceFill(['processing_status' => 'pending', 'processing_error' => null])->save();
        $rated = $rating->rate($bucket->refresh());

        return back()->with('status', $rated ? 'باکت با موفقیت دوباره قیمت‌گذاری شد.' : 'باکت هنوز شرایط قیمت‌گذاری را ندارد یا قبلاً پردازش شده است.');
    }

    public function reconcile(Request $request, NetworkUsageReconciliationService $service): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from'], 'vm_uuid' => ['nullable', 'uuid']]);
        $rows = $service->reconcile($data['from'], $data['to'], $data['vm_uuid'] ?? null);

        return redirect()->route('admin.billing.network.reconciliation')->withInput()->with('reconciliation', $rows);
    }

    public function reconciliation(): View
    {
        return view('admin.billing.network.reconciliation', ['rows' => session('reconciliation', [])]);
    }

    public function vm(VirtualMachine $virtualMachine, Request $request): View
    {
        $virtualMachine->load(['customer', 'project.owner', 'bundle']);
        $summary = $this->reports->vmSummary($virtualMachine);
        $period = $summary['period'];
        $from = $period?->period_start ?? CarbonImmutable::now()->startOfMonth();
        $to = $period?->period_end ?? CarbonImmutable::now()->endOfMonth();

        return view('admin.billing.network.vm', [
            'vm' => $virtualMachine, 'summary' => $summary, 'reports' => $this->reports, 'wallets' => $this->wallets,
            'daily' => $this->reports->daily($virtualMachine, $from, $to),
            'buckets' => NetworkUsageBucket::query()->where('virtual_machine_id', $virtualMachine->id)->with('ratings')->latest('interval_start')->paginate(25),
            'corrections' => NetworkUsageRating::query()->whereHas('bucket', fn ($q) => $q->where('virtual_machine_id', $virtualMachine->id))->where('revision', '>', 1)->latest()->limit(20)->get(),
        ]);
    }
}
