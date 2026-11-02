<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ColocationPlan;
use App\Models\ColocationService;
use App\Models\Invoice;
use App\Services\ColocationBillingService;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ColocationController extends Controller
{
    public function __construct(private readonly ProjectAccessService $projects, private readonly ColocationBillingService $billing) {}

    public function index(Request $request)
    {
        $project = $this->projects->activeProject($request, $request->user('customer'));
        abort_unless($this->projects->canViewBilling($project, $request->user('customer')), 404);
        $services = ColocationService::where('project_id', $project->id)->with('invoices')->latest()->get();
        abort_unless($project->owner->colocation_enabled || $services->isNotEmpty(), 404);

        return view('customer.colocation.index', $this->context($request, $project) + [
            'services' => $services,
            'plans' => $project->owner->colocation_enabled && ! $project->owner->isSuspended() ? $project->owner->colocationPlans()->where('available', true)->get() : collect(),
        ]);
    }

    public function checkout(Request $request, ColocationPlan $plan)
    {
        $project = $this->projects->activeProject($request, $request->user('customer'));
        abort_unless($this->projects->canViewBilling($project, $request->user('customer')) && $plan->customer_id === $project->owner_customer_id && $project->owner->colocation_enabled && ! $project->owner->isSuspended() && $plan->available, 404);

        return view('customer.colocation.checkout', $this->context($request, $project) + ['plan' => $plan, 'checkoutKey' => (string) Str::uuid(), 'planVersion' => hash('sha256', $plan->toJson())]);
    }

    public function store(Request $request, ColocationPlan $plan)
    {
        $project = $this->projects->activeProject($request, $request->user('customer'));
        abort_unless($this->projects->canViewBilling($project, $request->user('customer')), 404);
        $data = $request->validate([
            'workspace' => ['required', 'in:'.$project->uuid], 'name' => ['required', 'string', 'max:100'],
            'billing_interval' => ['required', 'in:monthly,quarterly,yearly'], 'checkout_key' => ['required', 'uuid'],
            'plan_version' => ['required', 'string'], 'accept_terms' => ['accepted'],
        ]);
        $service = DB::transaction(function () use ($plan, $data, $project) {
            $project->owner()->lockForUpdate()->firstOrFail();
            $plan = ColocationPlan::lockForUpdate()->findOrFail($plan->id);
            if (! hash_equals(hash('sha256', $plan->toJson()), $data['plan_version'])) {
                throw ValidationException::withMessages(['plan_version' => 'جزئیات پلن تغییر کرده است. صفحه را تازه‌سازی و مبلغ جدید را بررسی کنید.']);
            }

            return $this->billing->order($project, $plan, $data['billing_interval'], $data['name'], $data['checkout_key']);
        });

        return redirect()->to(route('customer.colocation.invoices.show', $service->invoices()->oldest('id')->firstOrFail(), false));
    }

    public function show(Request $request, ColocationService $service)
    {
        $project = $this->authorizeService($request, $service);

        return view('customer.colocation.show', $this->context($request, $project) + ['service' => $service->load(['invoices' => fn ($q) => $q->latest('id'), 'events' => fn ($q) => $q->latest('id')])]);
    }

    public function invoices(Request $request)
    {
        $project = $this->projects->activeProject($request, $request->user('customer'));
        abort_unless($this->projects->canViewBilling($project, $request->user('customer')), 404);
        abort_unless($project->owner->colocation_enabled || ColocationService::where('project_id', $project->id)->exists(), 404);

        return view('customer.colocation.invoices', $this->context($request, $project) + ['invoices' => Invoice::where('kind', 'colocation')->whereHas('colocationService', fn ($q) => $q->where('project_id', $project->id))->with('colocationService')->latest('id')->paginate(20)]);
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->kind === 'colocation' && $invoice->colocationService, 404);
        $project = $this->authorizeService($request, $invoice->colocationService);

        return view('customer.colocation.invoice', $this->context($request, $project) + [
            'invoice' => $invoice->load(['items', 'payments' => fn ($q) => $q->latest('id')]),
            'service' => $invoice->colocationService,
            'gateways' => array_intersect_key(app(PaymentGatewayManager::class)->available(), array_flip(['mellat', 'zibal'])),
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->kind === 'colocation' && $invoice->colocationService, 404);
        $this->authorizeService($request, $invoice->colocationService);
        abort_if($invoice->customer->isSuspended(), 403);
        $data = $request->validate(['gateway' => ['required', 'in:mellat,zibal']]);
        try {
            $payment = $this->billing->pay($invoice, $data['gateway']);
        } catch (PaymentGatewayException $exception) {
            report($exception);

            return back()->withErrors(['payment' => 'اتصال به درگاه برقرار نشد. لطفاً دوباره تلاش کنید.']);
        }

        return redirect()->to(route('customer.wallet.payments.gateway.show', $payment, false));
    }

    public function cancel(Request $request, ColocationService $service)
    {
        $this->authorizeService($request, $service);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($service, $data): void {
            $service = ColocationService::lockForUpdate()->findOrFail($service->id);
            abort_if($service->status === 'cancelled', 409);
            if (! $service->cancellation_requested_at) {
                $service->update(['cancellation_requested_at' => now(), 'cancellation_reason' => $data['reason']]);
                $this->billing->event($service, 'cancellation_requested', 'درخواست پایان سرویس ثبت شد. پشتیبانی تاریخ پایان و تحویل تجهیزات را هماهنگ می‌کند.');
            }
        });

        return back()->with('status', 'درخواست شما ثبت شد. تا تأیید تاریخ پایان، سرویس طبق قرارداد ادامه دارد.');
    }

    private function authorizeService(Request $request, ColocationService $service)
    {
        $project = $service->project;
        abort_unless($this->projects->canViewBilling($project, $request->user('customer')), 404);
        $this->projects->switch($request, $request->user('customer'), $project);

        return $project;
    }

    private function context(Request $request, $project): array
    {
        return ['customer' => $request->user('customer'), 'activeProject' => $project, 'wallets' => app(WalletService::class)];
    }
}
