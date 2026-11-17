@php
    $isAdmin = $portal === 'admin';
    $isInternal = $mode === 'internal';
    $editorId = "ticket-{$portal}-{$mode}";
    $sendLabel = $isInternal ? 'ثبت یادداشت داخلی' : ($isAdmin ? 'ارسال پاسخ به مشتری' : 'ارسال پاسخ');
@endphp

<form @unless($isAdmin) id="ticket-reply" x-data="{ submitting: false }" @endunless
    method="POST" action="{{ $isAdmin ? route('admin.tickets.reply', $ticket) : route('customer.tickets.reply', $ticket, false) }}"
    enctype="multipart/form-data" @submit="submitting = true"
    @class(['p-4 sm:p-5', 'border-t border-slate-200' => !$isAdmin, 'border-s-4 border-amber-300 bg-amber-50' => $isInternal])>
    @csrf
    @if($isAdmin)
        <input type="hidden" name="mode" value="{{ $mode }}">
        <p @class(['mb-2 text-xs font-bold', 'text-amber-800' => $isInternal, 'text-slate-600' => !$isInternal])>
            {{ $isInternal ? 'متن و فایل‌ها فقط برای تیم پشتیبانی قابل مشاهده‌اند.' : 'مشتری متن و فایل‌های این پاسخ را می‌بیند.' }}
        </p>
    @endif

    @if($restoreDraft && $errors->any())
        <ul class="mb-3 list-inside list-disc rounded-xl bg-red-50 px-4 py-3 text-sm leading-7 text-red-700" role="alert">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <label for="{{ $editorId }}" class="sr-only">{{ $isInternal ? 'متن یادداشت داخلی' : 'متن پاسخ' }}</label>
    <textarea id="{{ $editorId }}" name="body" rows="4" required dir="auto" data-ticket-editor
        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm leading-7"
        placeholder="{{ $isInternal ? 'یادداشت داخلی بنویسید…' : 'پاسخ خود را بنویسید…' }}">{{ $restoreDraft ? old('body') : '' }}</textarea>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-3 text-sm font-bold text-slate-600 hover:bg-slate-100 focus-within:ring-2 focus-within:ring-blue-500">
            <input type="file" name="attachments[]" multiple data-ticket-attachments data-ticket-removable
                accept="image/*,.pdf,.txt,.log,.csv,.json,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx" class="sr-only"
                aria-describedby="{{ $editorId }}-file-help">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="m21 11-8.6 8.6a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-2.8-2.8L16 6.4" stroke-linecap="round"/>
            </svg>
            <span>پیوست فایل</span>
        </label>
        <button type="submit" :disabled="submitting"
            @class(['min-h-11 rounded-xl px-5 py-2 text-sm font-black text-white disabled:cursor-wait disabled:opacity-60', 'bg-amber-700 hover:bg-amber-800' => $isInternal, 'bg-[#0069FF] hover:bg-[#0050D0]' => !$isInternal])>
            {{ $sendLabel }}
        </button>
    </div>
    <p id="{{ $editorId }}-file-help" class="mt-1 px-3 text-xs text-slate-500">حداکثر ۵ فایل، هر فایل ۲۰ مگابایت</p>
</form>
