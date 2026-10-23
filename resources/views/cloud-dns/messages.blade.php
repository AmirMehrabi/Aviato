@if(session('status'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm leading-7 text-emerald-900">{{ session('status') }}</div>@endif
@if($errors->any() && ! ($modalErrors ?? false))<div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm leading-7 text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if(session('dns_import'))
    @php($report = session('dns_import'))
    <div role="status" class="rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-sm leading-7 text-blue-900">
        <p class="font-bold">{{ $report['imported'] }} رکورد وارد شد · {{ $report['duplicates'] }} مقدار تکراری نادیده گرفته شد · {{ count($report['warnings']) }} مورد رد شد</p>
        @if($report['warnings'])<details class="mt-2"><summary class="cursor-pointer font-bold">مشاهده موارد ردشده</summary><ul class="mt-2 list-inside list-disc">@foreach($report['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></details>@endif
    </div>
@endif
