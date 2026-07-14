<div x-data="{ tab: 'curl' }" class="mt-6 overflow-hidden rounded-2xl border border-slate-800 bg-[#071B3A]" dir="ltr">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
        <span class="text-[10px] font-black uppercase tracking-[.16em] text-slate-400">Request examples</span>
        <div class="flex items-center gap-1 rounded-lg bg-white/10 p-1">
            @foreach (['curl' => 'cURL', 'php' => 'PHP', 'javascript' => 'JavaScript'] as $name => $label)
                <button type="button" @click="tab = '{{ $name }}'" :class="tab === '{{ $name }}' ? 'bg-white text-[#071B3A]' : 'text-slate-300 hover:bg-white/10'" class="rounded-md px-2.5 py-1.5 text-[11px] font-black transition">{{ $label }}</button>
            @endforeach
        </div>
    </div>
@foreach (['curl' => ['value' => $curl, 'label' => 'cURL'], 'php' => ['value' => $php, 'label' => 'PHP'], 'javascript' => ['value' => $javascript, 'label' => 'JavaScript']] as $name => $sample)
        @php($lines = preg_split('/\r\n|\r|\n/', $sample['value']))
        <div x-show="tab === '{{ $name }}'" x-cloak class="relative p-4">
            <button type="button" @click="copy(@js($sample['value']), '{{ $key }}-{{ $name }}')" class="absolute right-4 top-4 rounded-lg border border-white/15 px-2.5 py-1.5 text-[11px] font-black text-blue-200 transition hover:bg-white/10" x-text="copied === '{{ $key }}-{{ $name }}' ? 'Copied ✓' : 'Copy'">Copy</button>
            <pre class="api-docs-code overflow-x-auto pr-20 font-mono text-xs leading-7 text-emerald-300"><code><ol>@foreach ($lines as $line)<li><span>{{ $line }}</span></li>@endforeach</ol></code></pre>
        </div>
    @endforeach
</div>
