<?php

namespace App\Services\CloudDns;

use App\Jobs\SyncDnsZone;
use App\Models\Customer;
use App\Models\DnsZone;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloudDnsService
{
    public function __construct(private RecordValidator $validator, private DnsLookup $lookup) {}

    public function create(Project $project, string $name, Model $actor): DnsZone
    {
        abort_unless(config('cloud-dns.enabled'), 503);
        $name = $this->validator->domain($name);
        foreach (config('cloud-dns.reserved_domains', []) as $reserved) {
            if ($name === $reserved || str_ends_with($name, '.'.$reserved)) {
                throw ValidationException::withMessages(['name' => 'این دامنه برای زیرساخت آویاتو رزرو شده است.']);
            }
        }

        return DB::transaction(function () use ($project, $name, $actor): DnsZone {
            Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            if ($project->dnsZones()->where('status', '!=', 'deleted')->count() >= config('cloud-dns.plans.free.zone_limit')) {
                throw ValidationException::withMessages(['name' => 'به سقف تعداد دامنه‌های طرح رسیده‌اید.']);
            }
            if (DnsZone::where('name', $name)->exists()) {
                throw ValidationException::withMessages(['name' => 'این دامنه قبلاً ثبت شده است؛ برای انتقال با پشتیبانی تماس بگیرید.']);
            }
            $zone = $project->dnsZones()->create([
                'name' => $name,
            ]);
            $this->audit($zone, $actor, 'created', [], ['name' => $name]);

            return $zone;
        });
    }

    public function verify(DnsZone $zone, Model $actor): void
    {
        if ($zone->verified_at) {
            return;
        }
        $observed = $this->lookup->delegation($zone->name);
        $expected = config('cloud-dns.nameservers');
        sort($expected);
        if ($observed !== $expected) {
            throw ValidationException::withMessages(['verification' => 'نیم‌سرورهای دامنه هنوز به آویاتو واگذار نشده‌اند.']);
        }
        $this->mutate($zone, $actor, 'verified', function ($locked): void {
            $locked->verified_at = now();
            $locked->status = 'pending';
        });
    }

    public function check(DnsZone $zone, ?Model $actor = null): void
    {
        $observed = $this->lookup->delegation($zone->name);
        $expected = config('cloud-dns.nameservers');
        sort($expected);
        $connected = $observed === $expected;
        $zone->update(['observed_nameservers' => $observed, 'delegation_status' => $connected ? 'active' : 'pending', 'checked_at' => now()]);
        if ($connected && ! $zone->verified_at) {
            $this->mutate($zone, $actor ?? $zone->project->owner, 'verified', function ($locked): void {
                $locked->verified_at = now();
                $locked->status = 'pending';
            });
        }
    }

    public function saveRecords(DnsZone $zone, array $inputs, Model $actor): void
    {
        $records = array_map(fn ($input) => $this->validator->rrset($zone->name, $input), $inputs);
        $this->mutate($zone, $actor, 'records.updated', function ($locked) use ($records): void {
            $this->editable($locked);
            foreach ($records as $record) {
                $conflicts = $locked->recordSets()->where('name', $record['name']);
                if ($record['type'] === 'CNAME') {
                    $conflicts->where('type', '!=', 'CNAME');
                } else {
                    $conflicts->where('type', 'CNAME');
                }
                if ($conflicts->exists()) {
                    throw ValidationException::withMessages(['type' => 'CNAME نمی‌تواند هم‌زمان با رکورد دیگری در همان نام وجود داشته باشد.']);
                }
                $locked->recordSets()->updateOrCreate(['name' => $record['name'], 'type' => $record['type']], $record);
            }
            $count = $locked->recordSets()->get()->sum(fn ($r) => count($r->records));
            if ($count > config('cloud-dns.plans.'.$locked->plan.'.record_limit')) {
                throw ValidationException::withMessages(['content' => 'به سقف تعداد رکوردهای طرح رسیده‌اید.']);
            }
        });
    }

    public function previewImport(DnsZone $zone, string $text): array
    {
        $this->editable($zone);

        return $this->planImport($zone, $text)['report'];
    }

    public function importRecords(DnsZone $zone, string $text, Model $actor): array
    {
        $report = [];
        $this->mutate($zone, $actor, 'records.imported', function ($locked) use ($text, &$report): bool {
            $this->editable($locked);
            $plan = $this->planImport($locked, $text);
            $report = $plan['report'];
            foreach ($plan['changes'] as $record) {
                $locked->recordSets()->updateOrCreate(['name' => $record['name'], 'type' => $record['type']], $record);
            }

            return $report['imported'] > 0;
        });

        return $report;
    }

    /** Plan and apply share validation; a preview never writes or queues DNS work. */
    private function planImport(DnsZone $zone, string $text): array
    {
        $file = new ZoneFile;
        $inputs = $file->parse($zone->name, $text, true);
        $report = ['imported' => 0, 'duplicates' => 0, 'warnings' => $file->warnings, 'records' => []];
        if (preg_match('/cf-proxied\s*:\s*true|cf-flatten-cname/i', $text)) {
            $report['warnings'][] = 'این فایل تنظیمات پروکسی یا CNAME flattening در Cloudflare دارد. این قابلیت‌ها منتقل نمی‌شوند؛ اتصال مستقیم سرور و گواهی HTTPS را پیش از تغییر نیم‌سرورها بررسی کنید.';
        }
        $sets = $zone->recordSets()->get()->mapWithKeys(fn ($record) => [$record->name.'|'.$record->type => $record->only(['name', 'type', 'ttl', 'records'])])->all();
        $count = array_sum(array_map(fn ($set) => count($set['records']), $sets));
        $changes = [];
        foreach ($inputs as $input) {
            foreach (explode("\n", $input['content']) as $content) {
                $item = ['name' => $input['name'], 'type' => $input['type'], 'content' => $content];
                try {
                    $record = $this->validator->rrset($zone->name, [...$input, 'content' => $content]);
                    $key = $record['name'].'|'.$record['type'];
                    $existing = $sets[$key] ?? null;
                    $value = $record['records'][0];
                    if ($existing && in_array($value, $existing['records'], true)) {
                        $report['duplicates']++;
                        $report['records'][] = [...$item, 'action' => 'تکراری؛ بدون تغییر'];

                        continue;
                    }
                    $conflict = collect($sets)->contains(fn ($set) => $set['name'] === $record['name'] && ($record['type'] === 'CNAME' ? $set['type'] !== 'CNAME' : $set['type'] === 'CNAME'));
                    if ($conflict || ($record['type'] === 'CNAME' && $existing)) {
                        throw ValidationException::withMessages(['content' => 'مقصد CNAME با رکورد موجود تداخل دارد.']);
                    }
                    if ($count >= config('cloud-dns.plans.'.$zone->plan.'.record_limit') || ($existing && count($existing['records']) >= 100)) {
                        throw ValidationException::withMessages(['content' => 'سقف تعداد رکوردها تکمیل است.']);
                    }
                    $record['records'] = [...($existing['records'] ?? []), $value];
                    $record['ttl'] = $existing['ttl'] ?? $record['ttl'];
                    $sets[$key] = $changes[$key] = $record;
                    $count++;
                    $report['imported']++;
                    $report['records'][] = [...$item, 'action' => $existing ? 'افزودن مقدار؛ حفظ TTL فعلی' : 'افزودن رکورد'];
                } catch (ValidationException $exception) {
                    $reason = implode(' ', $exception->validator->errors()->all());
                    $report['warnings'][] = $input['name'].' '.$input['type'].': '.$reason;
                    $report['records'][] = [...$item, 'action' => 'رد شده: '.$reason];
                }
            }
        }

        return ['report' => $report, 'changes' => $changes];
    }

    public function deleteRecord(DnsZone $zone, int $recordId, Model $actor): void
    {
        $this->mutate($zone, $actor, 'records.deleted', function ($locked) use ($recordId): void {
            $this->editable($locked);
            $locked->recordSets()->findOrFail($recordId)->delete();
        });
    }

    public function remove(DnsZone $zone, Model $actor): void
    {
        $this->mutate($zone, $actor, 'deleted', function ($locked): void {
            $locked->delete_requested_at = now();
            $locked->status = $locked->verified_at ? 'deleting' : 'deleted';
            if (! $locked->verified_at) {
                $locked->name = $locked->uuid.'.deleted.invalid';
            }
        });
    }

    public function suspend(DnsZone $zone, bool $suspended, Model $actor): void
    {
        $this->mutate($zone, $actor, $suspended ? 'suspended' : 'resumed', function ($locked) use ($suspended): void {
            abort_unless($locked->verified_at, 422);
            $locked->status = $suspended ? 'suspended' : 'pending';
        });
    }

    public function retry(DnsZone $zone): void
    {
        abort_unless($zone->verified_at && $zone->status !== 'deleted', 422);
        SyncDnsZone::dispatch($zone->id);
    }

    private function editable(DnsZone $zone): void
    {
        abort_if(in_array($zone->status, ['suspended', 'deleted', 'deleting'], true), 409);
    }

    private function mutate(DnsZone $zone, Model $actor, string $action, callable $callback): void
    {
        DB::transaction(function () use ($zone, $actor, $action, $callback): void {
            $locked = DnsZone::whereKey($zone->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status === 'deleted' || $locked->delete_requested_at, 409);
            $before = $this->snapshot($locked);
            if ($callback($locked) === false) {
                return;
            }
            $locked->revision++;
            if ($locked->verified_at && ! in_array($locked->status, ['suspended', 'deleting', 'deleted'], true)) {
                $locked->status = 'pending';
            }
            $locked->save();
            $this->audit($locked, $actor, $action, $before, $this->snapshot($locked));
            if ($locked->verified_at) {
                SyncDnsZone::dispatch($locked->id)->afterCommit();
            }
        });
    }

    private function snapshot(DnsZone $zone): array
    {
        return ['name' => $zone->name, 'status' => $zone->status, 'records' => $zone->recordSets()->orderBy('name')->orderBy('type')->get(['name', 'type', 'ttl', 'records'])->toArray()];
    }

    private function audit(DnsZone $zone, Model $actor, string $action, array $before, array $after): void
    {
        $zone->changes()->create(['actor_type' => $actor instanceof Customer ? 'customer' : 'admin', 'actor_id' => $actor->id, 'action' => $action, 'revision' => $zone->revision, 'before' => $before, 'after' => $after]);
    }
}
