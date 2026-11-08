<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Services\VmHardwareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class VmConfigurationController extends Controller
{
    public function __construct(private readonly VmHardwareService $hardware) {}

    public function refresh(VirtualMachine $virtualMachine): RedirectResponse
    {
        abort_if($virtualMachine->isDeleted(), 404);
        try {
            $this->hardware->observe($virtualMachine);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->back($virtualMachine)->with('error', 'خواندن Proxmox انجام نشد. اطلاعات قبلی حفظ شده است؛ اتصال و دسترسی API را بررسی و دوباره تلاش کنید.');
        }

        return $this->back($virtualMachine)->with('status', 'سخت‌افزار Proxmox بررسی شد. برای ثبت اختلاف‌ها در پنل، «پذیرش سخت‌افزار» را انتخاب کنید.');
    }

    public function preview(Request $request, VirtualMachine $virtualMachine): View|RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['pricing', 'sync', 'hardware'])],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'cpu_cores' => ['required_if:kind,hardware', 'integer', 'min:1', 'max:512'],
            'ram_gb' => ['required_if:kind,hardware', 'integer', 'min:1', 'max:1048576'],
            'disk_gb' => ['required_if:kind,hardware', 'integer', 'min:1', 'max:1048576'],
            'pricing_mode' => ['required_if:kind,pricing', Rule::in(['inherit', 'custom', 'resources'])],
            'custom_monthly_price' => ['nullable', 'required_if:pricing_mode,custom', 'integer', 'min:0', 'max:1000000000000'],
            'custom_stopped_monthly_price' => ['nullable', 'required_if:pricing_mode,custom', 'integer', 'min:0', 'max:1000000000000', 'lte:custom_monthly_price'],
        ]);
        $actor = $request->user('admin');
        abort_unless($actor->allows($data['kind'] === 'pricing' ? 'pricing.manage' : 'virtual-machines.manage'), 403);
        if ($data['kind'] === 'hardware') {
            abort_unless($actor->allows('virtual-machines.power'), 403);
        }
        try {
            $quote = $this->hardware->quote($virtualMachine, $actor, $data['kind'], $data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->back($virtualMachine)->with('error', 'پیش‌نمایش آماده نشد. اتصال زیرساخت و نرخ‌های قیمت‌گذاری را بررسی کنید.');
        }
        $request->session()->flashInput($request->except('_token'));

        return view('admin.virtual-machines.configuration-review', ['vm' => $virtualMachine, 'quote' => $quote]);
    }

    public function confirm(Request $request, VirtualMachine $virtualMachine): RedirectResponse
    {
        $data = $request->validate(['quote' => ['required', 'string'], 'confirm' => ['accepted']]);
        try {
            $order = $this->hardware->confirm($virtualMachine, $request->user('admin'), $data['quote']);
        } catch (ValidationException $exception) {
            return $this->back($virtualMachine)->withErrors($exception->errors());
        } catch (HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return $this->back($virtualMachine)->with('error', 'ثبت تغییرات تأیید نشد. وضعیت همین صفحه را بررسی و پیش‌نمایش تازه دریافت کنید.');
        }

        return $this->back($virtualMachine)->with('status', $order
            ? 'درخواست تغییر سخت‌افزار ثبت شد. پیشرفت عملیات در همین صفحه نمایش داده می‌شود؛ بستن صفحه درخواست را لغو نمی‌کند.'
            : 'تغییرات ثبت شد. کارکرد گذشته با قیمت قبلی حفظ شده و تنظیم جدید از اکنون اعمال می‌شود.');
    }

    private function back(VirtualMachine $vm): RedirectResponse
    {
        return redirect()->route('admin.virtual-machines.show', $vm)->withFragment('configuration');
    }
}
