@php
    $startedAt = is_object($incident->started_at) ? $incident->started_at->format('Y-m-d\\TH:i') : $incident->started_at;
    $endedAt = is_object($incident->ended_at) ? $incident->ended_at->format('Y-m-d\\TH:i') : $incident->ended_at;
@endphp
<div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
    <div class="grid gap-5 md:grid-cols-2">
        <label>
            <span class="text-sm font-black text-slate-700">عنوان</span>
            <input name="title" value="{{ old('title', $incident->title) }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">
        </label>
        <label>
            <span class="text-sm font-black text-slate-700">نامک</span>
            <input name="slug" value="{{ old('slug', $incident->slug) }}" placeholder="خودکار تولید می‌شود" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3" dir="ltr">
        </label>
        <label>
            <span class="text-sm font-black text-slate-700">سرویس تحت تأثیر</span>
            <input name="affected_service" value="{{ old('affected_service', $incident->affected_service) }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">
        </label>
        <label>
            <span class="text-sm font-black text-slate-700">وضعیت</span>
            <select name="status" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">
                @foreach (\App\Models\Incident::STATUSES as $status)
                    <option value="{{ $status }}" @selected(old('status', $incident->status) === $status)>{{ match($status) {
                        'investigating' => 'در حال بررسی',
                        'identified' => 'شناسایی شده',
                        'monitoring' => 'پایش',
                        'resolved' => 'رفع شده',
                        default => ucfirst($status),
                    } }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span class="text-sm font-black text-slate-700">زمان شروع</span>
            <input type="datetime-local" name="started_at" value="{{ old('started_at', $startedAt) }}" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">
        </label>
        <label>
            <span class="text-sm font-black text-slate-700">زمان پایان</span>
            <input type="datetime-local" name="ended_at" value="{{ old('ended_at', $endedAt) }}" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">
        </label>
    </div>

    <label class="block">
        <span class="text-sm font-black text-slate-700">خلاصه تأثیر</span>
        <textarea name="impact_summary" rows="3" required class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">{{ old('impact_summary', $incident->impact_summary) }}</textarea>
    </label>

    <div class="grid gap-5 md:grid-cols-2">
        @foreach ([['summary','خلاصه'],['root_cause','علت ریشه‌ای'],['customer_impact','تأثیر بر مشتریان'],['resolution','رفع مشکل'],['next_steps','اقدامات بعدی'],['final_status','وضعیت نهایی']] as [$field,$label])
            <label class="block">
                <span class="text-sm font-black text-slate-700">{{ $label }}</span>
                <textarea name="{{ $field }}" rows="5" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">{{ old($field, $incident->{$field}) }}</textarea>
            </label>
        @endforeach
    </div>

    <label class="block">
        <span class="text-sm font-black text-slate-700">توضیحات متا</span>
        <textarea name="meta_description" rows="2" maxlength="320" class="mt-2 w-full rounded-lg border border-slate-200 px-4 py-3">{{ old('meta_description', $incident->meta_description) }}</textarea>
    </label>

    <label class="flex items-center gap-3">
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $incident->is_published)) class="size-4 rounded border-slate-300 text-[#0069FF]">
        <span class="text-sm font-black text-slate-700">انتشار عمومی این گزارش رخداد</span>
    </label>
</div>
