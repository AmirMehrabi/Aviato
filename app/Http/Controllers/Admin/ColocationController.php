<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ColocationPlan;
use App\Models\ColocationService;
use App\Models\Customer;
use App\Services\ColocationBillingService;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ColocationController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(array_keys(ColocationService::STATUSES))]]);
        $services = ColocationService::with(['customer', 'project', 'invoices'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')->paginate(25)->withQueryString();
        $customers = Customer::query()->when($filters['q'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))->orderByDesc('colocation_enabled')->orderBy('name')->limit(30)->get();

        return view('admin.colocation.index', compact('services', 'customers'));
    }

    public function customer(Customer $customer)
    {
        return view('admin.colocation.customer', ['customer' => $customer, 'plans' => $customer->colocationPlans()->latest('id')->get(), 'services' => $customer->colocationServices()->with('project')->latest('id')->get(), 'wallets' => app(WalletService::class)]);
    }

    public function access(Request $request, Customer $customer)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        if ($data['enabled'] && ! $customer->colocationPlans()->where('available', true)->exists()) {
            throw ValidationException::withMessages(['enabled' => 'ابتدا حداقل یک پلن قابل سفارش برای مشتری تعریف کنید.']);
        }
        $customer->forceFill(['colocation_enabled' => (bool) $data['enabled']])->save();

        return back()->with('status', 'دسترسی هم‌مکانی ذخیره شد. سرویس‌ها و صورتحساب‌های قبلی حفظ می‌شوند.');
    }

    public function plan(Request $request, Customer $customer, ?ColocationPlan $plan = null)
    {
        abort_if($plan && $plan->customer_id !== $customer->id, 404);
        $rules = [
            'name' => ['required', 'string', 'max:100'], 'location' => ['required', 'string', 'max:150'],
            'rack_units' => ['required', 'integer', 'min:1', 'max:48'], 'power' => ['required', 'string', 'max:255'],
            'connectivity' => ['required', 'string', 'max:255'], 'traffic_policy' => ['required', Rule::in(array_keys(ColocationPlan::TRAFFIC_POLICIES))],
            'included_traffic_gb' => ['required', 'integer', 'min:0', 'max:10000000'],
            'traffic_price_per_gb_toman' => ['required', 'integer', 'min:0', 'max:100000000'],
            'setup_fee_toman' => ['required', 'integer', 'min:0', 'max:9000000000'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'terms' => ['required', 'string', 'max:10000'], 'delivery_instructions' => ['nullable', 'string', 'max:5000'],
            'available' => ['required', 'boolean'],
        ];
        foreach (ColocationPlan::INTERVALS as $interval => $label) {
            $rules[$interval.'_price_toman'] = ['nullable', 'integer', 'min:1', 'max:9000000000'];
        }
        $data = $request->validate($rules);
        if (collect(array_keys(ColocationPlan::INTERVALS))->every(fn ($key) => ($data[$key.'_price_toman'] ?? null) === null)) {
            throw ValidationException::withMessages(['monthly_price_toman' => 'حداقل یک دوره پرداخت و مبلغ آن را وارد کنید.']);
        }
        if ($data['traffic_policy'] === 'included' && (int) $data['included_traffic_gb'] === 0) {
            throw ValidationException::withMessages(['included_traffic_gb' => 'سهمیه ماهانه را وارد کنید.']);
        }
        if (in_array($data['traffic_policy'], ['included', 'metered'], true) && (int) $data['traffic_price_per_gb_toman'] === 0) {
            throw ValidationException::withMessages(['traffic_price_per_gb_toman' => 'نرخ ترافیک قابل محاسبه باید بیشتر از صفر باشد.']);
        }
        foreach (['monthly_price', 'quarterly_price', 'yearly_price', 'setup_fee', 'traffic_price_per_gb'] as $field) {
            $data[$field] = isset($data[$field.'_toman']) ? (int) $data[$field.'_toman'] * 10 : null;
            unset($data[$field.'_toman']);
        }
        if ($data['traffic_policy'] !== 'included') {
            $data['included_traffic_gb'] = 0;
        }
        if (in_array($data['traffic_policy'], ['unmetered', 'separate'], true)) {
            $data['traffic_price_per_gb'] = 0;
        }
        $data['customer_id'] = $customer->id;
        $data['currency'] = 'IRR';
        $plan ? $plan->update($data) : ColocationPlan::create($data);

        return back()->with('status', 'پلن اختصاصی ذخیره شد. قرارداد سرویس‌های خریداری‌شده تغییر نمی‌کند.');
    }

    public function show(ColocationService $service)
    {
        return view('admin.colocation.show', ['service' => $service->load(['customer', 'project', 'invoices.items', 'events']), 'wallets' => app(WalletService::class)]);
    }

    public function progress(Request $request, ColocationService $service, ColocationBillingService $billing)
    {
        $data = $request->validate(['status' => ['required', 'in:awaiting_installation,received,installing,testing'], 'message' => ['required', 'string', 'max:2000'], 'equipment' => ['nullable', 'string', 'max:10000']]);
        $billing->progress($service, $data['status'], $data['message'], $data['equipment'] ?? null, $request->user('admin')->id);

        return back()->with('status', 'پیشرفت نصب برای مشتری ثبت شد.');
    }

    public function activate(Request $request, ColocationService $service, ColocationBillingService $billing)
    {
        $data = $request->validate(['billing_starts_at' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:2000'], 'confirm_activation' => ['accepted']]);
        $billing->activate($service, CarbonImmutable::createFromFormat('!Y-m-d', $data['billing_starts_at'], 'Asia/Tehran'), $data['reason'] ?? '', $request->user('admin')->id);

        return back()->with('status', 'نصب تکمیل شد و تاریخ شروع صورتحساب ثبت شد.');
    }

    public function traffic(Request $request, ColocationService $service, ColocationBillingService $billing)
    {
        $data = $request->validate(['month' => ['required', 'integer', 'min:0', 'max:1200'], 'usage_gb' => ['required', 'numeric', 'min:0', 'max:100000000', 'decimal:0,3'], 'source' => ['required', 'string', 'max:2000'], 'confirm_traffic' => ['accepted']]);
        $billing->traffic($service, (int) $data['month'], (int) round((float) $data['usage_gb'] * 1000), $data['source'], $request->user('admin')->id);

        return back()->with('status', 'مصرف نهایی ثبت و صورتحساب ترافیک صادر شد.');
    }

    public function end(Request $request, ColocationService $service, ColocationBillingService $billing)
    {
        $data = $request->validate(['ends_at' => ['required', 'date'], 'message' => ['required', 'string', 'max:2000'], 'confirm_end' => ['accepted']]);
        $billing->end($service, CarbonImmutable::parse($data['ends_at']), $data['message'], $request->user('admin')->id);

        return back()->with('status', 'تاریخ پایان سرویس ثبت شد. پس از آن دوره جدید صادر نمی‌شود.');
    }
}
