@if($message->attachments->isNotEmpty())
    <div class="mt-5 grid gap-2 sm:grid-cols-2">
        @foreach($message->attachments as $attachment)
            @php
                $isImage = str_starts_with((string) $attachment->mime_type, 'image/');
                $isPdf = $attachment->mime_type === 'application/pdf' || str_ends_with(strtolower($attachment->original_name), '.pdf');
            @endphp
            <a href="{{ route('customer.tickets.attachments.show', [$ticket, $attachment], false) }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-right transition hover:border-[#B8D6FF] hover:bg-[#F8FBFF]">
                <span class="grid size-10 shrink-0 place-items-center rounded-lg {{ $isImage ? 'bg-emerald-50 text-emerald-700' : ($isPdf ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-600') }}">
                    @if($isImage)
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4V5Zm3 11 3.5-4 2.5 3 2-2.5L19 16" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9" cy="9" r="1.5"/></svg>
                    @elseif($isPdf)
                        <span class="text-[10px] font-black">PDF</span>
                    @else
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
                    @endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-xs font-black text-slate-800">{{ $attachment->original_name }}</span>
                    <span class="mt-1 block text-[11px] font-bold text-slate-400" dir="ltr">{{ $attachment->readableSize() }}</span>
                </span>
                <span class="shrink-0 text-xs font-black text-[#0069FF] opacity-0 transition group-hover:opacity-100">دانلود</span>
            </a>
        @endforeach
    </div>
@endif
