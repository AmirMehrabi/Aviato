<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DnsZone;
use App\Services\CloudDns\CloudDnsService;
use App\Services\CloudDns\RecordValidator;
use App\Services\CloudDns\ZoneFile;
use App\Services\ProjectAccessService;
use App\Services\WalletService;
use Illuminate\Http\Request;

class CloudDnsController extends Controller
{
    public function __construct(private ProjectAccessService $projects, private CloudDnsService $dns) {}

    public function index(Request $request)
    {
        [$project, $membership] = $this->access($request);

        return view('customer.cloud-dns.index', [
            'wallets' => app(WalletService::class),
            'customer' => $request->user('customer'), 'activeProject' => $project, 'activeMembership' => $membership,
            'zones' => $project->dnsZones()->where('status', '!=', 'deleted')->withCount('recordSets')->latest()->paginate(20),
            'canManage' => $membership->canManageDns(),
        ]);
    }

    public function store(Request $request)
    {
        [$project] = $this->access($request, true);
        $data = $request->validate(['name' => ['required', 'string', 'max:253']]);
        $zone = $this->dns->create($project, $data['name'], $request->user('customer'));

        return redirect()->route('customer.cloud-dns.show', $zone);
    }

    public function show(Request $request, DnsZone $zone)
    {
        [$project, $membership] = $this->access($request, false, $zone);
        $zone->load(['recordSets' => fn ($q) => $q->orderBy('name')->orderBy('type')]);

        return view('customer.cloud-dns.show', [
            'wallets' => app(WalletService::class),
            'customer' => $request->user('customer'), 'activeProject' => $project, 'activeMembership' => $membership,
            'zone' => $zone, 'canManage' => $membership->canManageDns(), 'types' => RecordValidator::TYPES,
            'changes' => $zone->changes()->latest('id')->paginate(15), 'admin' => false,
        ]);
    }

    public function verify(Request $request, DnsZone $zone)
    {
        $this->access($request, true, $zone);
        $this->dns->verify($zone, $request->user('customer'));

        return back()->with('status', 'مالکیت تأیید شد؛ آماده‌سازی DNS در صف قرار گرفت.');
    }

    public function check(Request $request, DnsZone $zone)
    {
        $this->access($request, true, $zone);
        try {
            $this->dns->check($zone);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['dns' => 'بررسی NS والد کامل نشد؛ دوباره تلاش کنید.']);
        }

        return back()->with('status', 'وضعیت واگذاری دامنه بررسی شد.');
    }

    public function records(Request $request, DnsZone $zone)
    {
        $this->access($request, true, $zone);
        $this->dns->saveRecords($zone, [$request->only(['name', 'type', 'ttl', 'content'])], $request->user('customer'));

        return back()->with('status', 'مجموعه رکورد ذخیره شد؛ وضعیت انتشار را بررسی کنید.');
    }

    public function deleteRecord(Request $request, DnsZone $zone, int $record)
    {
        $this->access($request, true, $zone);
        $this->dns->deleteRecord($zone, $record, $request->user('customer'));

        return back()->with('status', 'حذف رکورد ثبت شد.');
    }

    public function import(Request $request, DnsZone $zone, ZoneFile $file)
    {
        $this->access($request, true, $zone);
        $data = $request->validate(['zone_file' => ['required', 'string', 'max:250000']]);
        $this->dns->saveRecords($zone, $file->parse($zone->name, $data['zone_file']), $request->user('customer'));

        return back()->with('status', 'ورودی ثبت شد؛ SOA و NS ریشه توسط آویاتو مدیریت می‌شوند.');
    }

    public function export(Request $request, DnsZone $zone, ZoneFile $file)
    {
        $this->access($request, false, $zone);

        return response($file->export($zone))->header('Content-Type', 'text/plain; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="'.$zone->name.'.zone"');
    }

    public function destroy(Request $request, DnsZone $zone)
    {
        $this->access($request, true, $zone);
        $this->dns->remove($zone, $request->user('customer'));

        return redirect()->route('customer.cloud-dns.index')->with('status', 'درخواست حذف ثبت شد؛ حذف از هر دو سرور تکمیل خواهد شد.');
    }

    private function access(Request $request, bool $manage = false, ?DnsZone $zone = null): array
    {
        $customer = $request->user('customer');
        $project = $this->projects->activeProject($request, $customer);
        $membership = $this->projects->membership($project, $customer);
        abort_unless($membership && ($manage ? $membership->canManageDns() : $membership->canReadDns()), 404);
        if ($zone) {
            abort_unless((int) $zone->project_id === (int) $project->id && $zone->status !== 'deleted', 404);
        }

        return [$project, $membership];
    }
}
