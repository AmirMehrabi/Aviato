@extends('layouts.admin')
@section('title', 'IP Pool جدید')
@section('content')
    @php
        $draftStartIp = old('start_ip', $pool->start_ip);
        $draftEndIp = old('end_ip', $pool->end_ip);
        $draftPreview = [
            'count' => 0,
            'first' => null,
            'last' => null,
            'range' => null,
            'ready' => false,
        ];

        if ($draftStartIp) {
            $start = ip2long($draftStartIp);
            $end = ip2long($draftEndIp ?: $draftStartIp);

            if ($start !== false && $end !== false && $end >= $start && ($end - $start) <= 4096) {
                $draftPreview['count'] = ($end - $start) + 1;
                $draftPreview['first'] = long2ip($start);
                $draftPreview['last'] = long2ip($end);
                $draftPreview['range'] = $draftPreview['first'] === $draftPreview['last']
                    ? $draftPreview['first']
                    : $draftPreview['first'].' - '.$draftPreview['last'];
                $draftPreview['ready'] = true;
            }
        }
    @endphp

    <div class="px-4 py-6 md:px-8 lg:px-10">
        @if ($errors->has('reservation'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first('reservation') }}</div>
        @endif

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_340px]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 class="text-2xl font-black">IP Pool جدید</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-500">
                            مشخصات شبکه را ثبت کنید. بعد از ذخیره، inventory واقعی IPها ساخته می‌شود و مستقیماً وارد صفحه ویرایش می‌شوید.
                        </p>
                    </div>
                    <span class="rounded-md bg-[#EBF3FF] px-2.5 py-1 text-xs font-black text-[#0069FF]">Preview only</span>
                </div>

                <form method="POST" action="{{ route('admin.ip-pools.store') }}" class="mt-6">
                    @include('admin.ip-pools._form')
                </form>
            </section>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-[#B8D6FF] bg-[#031B4E] p-5 text-white shadow-sm">
                    <p class="text-xs font-black text-white/60">Range Preview</p>
                    @if ($draftPreview['ready'])
                        <p class="mt-2 text-2xl font-black">{{ $draftPreview['count'] }} IP</p>
                        <p class="mt-2 font-mono text-sm text-white/75" dir="ltr">{{ $draftPreview['range'] }}</p>
                        <p class="mt-4 text-xs leading-6 text-white/65">
                            This range will be materialized on save, then available for manual reservations and automatic provisioning.
                        </p>
                    @else
                        <p class="mt-2 text-2xl font-black">—</p>
                        <p class="mt-2 text-sm leading-7 text-white/70">
                            Enter a start IP, and optionally an end IP, to see the exact address count before you save the pool.
                        </p>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-black text-slate-500">What happens after save</p>
                    <ul class="mt-3 space-y-3 text-sm leading-7 text-slate-600">
                        <li>• The pool is stored with the selected Proxmox server and bridge.</li>
                        <li>• The address inventory is generated from the configured range.</li>
                        <li>• You land on the edit page, where used, reserved, released, and free IPs are visible.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
@endsection
