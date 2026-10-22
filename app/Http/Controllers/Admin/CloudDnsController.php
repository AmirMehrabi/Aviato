<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DnsZone;
use App\Services\CloudDns\CloudDnsService;
use App\Services\CloudDns\PowerDnsClient;
use App\Services\CloudDns\RecordValidator;
use App\Services\CloudDns\ZoneFile;
use App\Support\AdminAccess;
use Illuminate\Http\Request;

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

        return view('admin.cloud-dns.index', compact('zones', 'health', 'search'));
    }

    public function show(Request $request, DnsZone $zone)
    {
        abort_if($zone->status === 'deleted', 404);
        $zone->load(['project.owner', 'recordSets' => fn ($q) => $q->orderBy('name')->orderBy('type')]);

        return view('admin.cloud-dns.show', [
            'zone' => $zone, 'canManage' => AdminAccess::allows($request->user('admin'), 'dns.manage'),
            'changes' => $zone->changes()->latest('id')->paginate(15), 'types' => RecordValidator::TYPES, 'admin' => true,
        ]);
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

    public function import(Request $request, DnsZone $zone, ZoneFile $file)
    {
        $data = $request->validate(['zone_file' => ['required', 'string', 'max:250000']]);
        $this->dns->saveRecords($zone, $file->parse($zone->name, $data['zone_file']), $request->user('admin'));

        return back()->with('status', 'ورودی ثبت شد.');
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
