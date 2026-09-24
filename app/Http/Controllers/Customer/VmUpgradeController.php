<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProjectAccessService;
use App\Services\VmActivityRecorder;
use App\Services\VmUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class VmUpgradeController extends Controller
{
    public function __construct(
        private readonly VmUpgradeService $upgrades,
        private readonly ProjectAccessService $projects,
        private readonly VmActivityRecorder $activities,
    ) {}

    public function storeBundle(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $customer = $request->user('customer');
        $virtualMachine = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);

        $data = $request->validate([
            'vm_bundle_id' => ['required', 'integer', 'exists:vm_bundles,id'],
        ]);

        try {
            $bundle = VmBundle::query()->where('is_active', true)->findOrFail($data['vm_bundle_id']);
            $order = $this->upgrades->requestBundleUpgrade($customer, $virtualMachine, $bundle);
            $this->activities->record($virtualMachine, 'upgrade', 'requested', 'درخواست ارتقای پلن ثبت شد', 'پلن مقصد: '.$bundle->name, $customer, ['order_id' => $order->id]);

            return back()->with('status', 'درخواست ارتقای باندل ثبت شد. سرور برای اعمال ارتقا کامل خاموش می شود و بعد از چند ثانیه دوباره روشن خواهد شد.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'ارتقای منابع ثبت نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.');
        }
    }

    public function storeExtraDisk(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $customer = $request->user('customer');
        $virtualMachine = $this->projects->resolveCustomerVm($request, $virtualMachine, manage: true);

        $data = $request->validate([
            'size_gb' => ['required', 'integer', 'in:10,25,50,100,250,500'],
        ]);

        try {
            $order = $this->upgrades->requestExtraDisk($customer, $virtualMachine, (int) $data['size_gb']);
            $this->activities->record($virtualMachine, 'upgrade', 'requested', 'درخواست دیسک اضافه ثبت شد', $data['size_gb'].' گیگابایت', $customer, ['order_id' => $order->id]);

            return back()->with('status', 'درخواست اتصال دیسک اضافه ثبت شد. بعد از آماده شدن، دیسک باید داخل سیستم عامل mount شود.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'دیسک اضافه ثبت نشد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.');
        }
    }
}
