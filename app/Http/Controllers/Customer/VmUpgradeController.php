<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\VmUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class VmUpgradeController extends Controller
{
    public function __construct(private readonly VmUpgradeService $upgrades) {}

    public function storeBundle(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_if((int) $virtualMachine->customer_id !== (int) $customer->id || $virtualMachine->isDeleted(), 404);

        $data = $request->validate([
            'vm_bundle_id' => ['required', 'integer', 'exists:vm_bundles,id'],
        ]);

        try {
            $bundle = VmBundle::query()->where('is_active', true)->findOrFail($data['vm_bundle_id']);
            $this->upgrades->requestBundleUpgrade($customer, $virtualMachine, $bundle);

            return back()->with('status', 'درخواست ارتقای باندل ثبت شد. تا پایان اعمال ارتقا، عملیات دیگر روی این سرور محدود می شود.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()->with('error', 'ثبت ارتقای باندل ممکن نیست: '.$exception->getMessage());
        }
    }

    public function storeExtraDisk(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_if((int) $virtualMachine->customer_id !== (int) $customer->id || $virtualMachine->isDeleted(), 404);

        $data = $request->validate([
            'size_gb' => ['required', 'integer', 'in:10,25,50,100,250,500'],
        ]);

        try {
            $this->upgrades->requestExtraDisk($customer, $virtualMachine, (int) $data['size_gb']);

            return back()->with('status', 'درخواست اتصال دیسک اضافه ثبت شد. بعد از آماده شدن، دیسک باید داخل سیستم عامل mount شود.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return back()->with('error', 'ثبت دیسک اضافه ممکن نیست: '.$exception->getMessage());
        }
    }
}
