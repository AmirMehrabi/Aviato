<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DnsZone;
use App\Services\CloudDns\CloudDnsRollout;
use App\Services\CloudDns\CloudDnsService;
use App\Services\CloudDns\PowerDnsClient;
use App\Services\CloudDns\RecordValidator;
use App\Services\CloudDns\ZoneFile;
use App\Support\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CloudDnsController extends Controller
{
    public function __construct(private CloudDnsService $dns) {}

    public function index(Request $request, PowerDnsClient $client)
    {
        $search = $request->validate(['search' => ['nullable', 'string', 'max:253']])['search'] ?? '';
        $zones = DnsZone::where('status', '!=', 'deleted')->with('project.owner')->withCount('recordSets')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('project', fn ($p) => $p->where('name', 'like', '%'.$search.'%')->orWhereHas('owner', fn ($c) => $c->where('email', 'like', '%'.$search.'%')));
            }))->latest()->paginate(25)->withQueryString();
        $health = cache()->remember('cloud-dns:health', 30, function () use ($client): array {
            $health = [];
            foreach (['primary', 'secondary'] as $node) {
                try {
                    $response = $client->request($node, 'GET');
                    $health[$node] = $response->successful() ? 'متصل · PowerDNS '.$response->json('version') : 'پاسخ نامعتبر';
                } catch (\Throwable $e) {
                    $health[$node] = 'اتصال ناموفق';
                }
            }

            return $health;
        });

        $rollout = app(CloudDnsRollout::class)->configuration();
        $canManageRollout = AdminAccess::allows($request->user('admin'), 'dns.rollout.manage');
        $rolloutCustomers = $canManageRollout ? Customer::whereIn('id', $rollout['customer_ids'])->orderBy('name')->get(['id', 'name', 'email', 'phone']) : collect();

        return view('admin.cloud-dns.index', compact('zones', 'health', 'search', 'rollout', 'canManageRollout', 'rolloutCustomers'));
    }

    public function updateRollout(Request $request, CloudDnsRollout $rollout)
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['mode', 'add', 'remove'])],
            'mode' => ['required_if:action,mode', Rule::in(CloudDnsRollout::MODES)],
            'identifier' => ['required_if:action,add', 'string', 'max:255'],
            'customer_id' => ['required_if:action,remove', 'integer', 'exists:customers,id'],
        ]);
        $customerId = isset($data['customer_id']) ? (int) $data['customer_id'] : null;
        if ($data['action'] === 'add') {
            $identifier = trim($data['identifier']);
            $customer = Customer::where('phone', $identifier)->orWhere('email', $identifier)->first();
            if (! $customer) {
                return back()->withInput()->withErrors(['identifier' => 'مشتری با این شماره موبایل یا ایمیل پیدا نشد.']);
            }
            $customerId = $customer->id;
        }
        $rollout->update($data['action'], $data['mode'] ?? null, $customerId);

        return back()->with('status', 'تنظیمات انتشار Cloud DNS ذخیره شد و از درخواست بعدی اعمال می‌شود.');
    }

    public function show(Request $request, DnsZone $zone)
    {
        abort_if($zone->status === 'deleted', 404);
        $zone->load(['project.owner', 'recordSets' => fn ($q) => $q->orderBy('name')->orderBy('type')]);

        $management = $request->routeIs('*.management');

        return view('admin.cloud-dns.show', [
            'zone' => $zone, 'canManage' => AdminAccess::allows($request->user('admin'), 'dns.manage'),
            'management' => $management, 'changes' => $management ? $zone->changes()->latest('id')->paginate(15) : null, 'types' => RecordValidator::TYPES, 'admin' => true,
        ]);
    }

    public function management(Request $request, DnsZone $zone)
    {
        return $this->show($request, $zone);
    }

    public function records(Request $request, DnsZone $zone)
    {
        $this->dns->saveRecords($zone, [$request->only(['name', 'type', 'ttl', 'content'])], $request->user('admin'));

        return back()->with('status', 'رکورد ذخیره شد.');
    }

    public function deleteRecord(Request $request, DnsZone $zone, int $record)
    {
        $this->dns->deleteRecord($zone, $record, $request->user('admin'));

        return back()->with('status', 'حذف رکورد ثبت شد.');
    }

    public function retry(Request $request, DnsZone $zone)
    {
        $this->dns->retry($zone);

        return back()->with('status', 'همگام‌سازی در صف قرار گرفت.');
    }

    public function suspension(Request $request, DnsZone $zone)
    {
        $data = $request->validate(['suspended' => ['required', 'boolean']]);
        $this->dns->suspend($zone, (bool) $data['suspended'], $request->user('admin'));

        return back()->with('status', 'تغییر وضعیت ثبت شد؛ وضعیت هر دو سرور را بررسی کنید.');
    }

    public function import(Request $request, DnsZone $zone)
    {
        $data = $request->validate(['zone_file' => ['required', 'string', 'max:250000']]);
        $report = $this->dns->importRecords($zone, $data['zone_file'], $request->user('admin'));

        return back()->with('dns_import', $report);
    }

    public function export(Request $request, DnsZone $zone, ZoneFile $file)
    {
        abort_if($zone->status === 'deleted', 404);

        return response($file->export($zone))->header('Content-Type', 'text/plain; charset=UTF-8')->header('Content-Disposition', 'attachment; filename="'.$zone->name.'.zone"');
    }

    public function destroy(Request $request, DnsZone $zone)
    {
        $this->dns->remove($zone, $request->user('admin'));

        return redirect()->route('admin.cloud-dns.index')->with('status', 'درخواست حذف ثبت شد.');
    }
}
