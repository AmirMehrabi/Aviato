@php
    $maxNameservers = $maxNameservers ?? 6;
    $serverInputs = old('nameservers', $initialNameservers ?? []);
    $serverInputs = is_array($serverInputs) ? array_values(array_filter($serverInputs, 'is_string')) : [];
    $serverInputs = array_pad(array_slice($serverInputs, 0, $maxNameservers), 2, '');
@endphp
<fieldset x-data="{ nameservers: @js($serverInputs) }" class="space-y-4">
    <legend class="sr-only">نیم‌سرورهای دامنه</legend>
    <template x-for="(nameserver, index) in nameservers" :key="index">
        <div>
            <label :for="'nameserver-' + index" class="domain-label" x-text="'نیم‌سرور ' + (index + 1)"></label>
            <div class="flex items-center gap-2"><input :id="'nameserver-' + index" name="nameservers[]" type="text" dir="ltr" x-model="nameservers[index]" required maxlength="253" :placeholder="'ns' + (index + 1) + '.your-dns.com'" class="domain-input" autocapitalize="none" spellcheck="false"><button type="button" x-show="nameservers.length > 2" @click="nameservers.splice(index, 1)" :aria-label="'حذف نیم‌سرور ' + (index + 1)" class="shrink-0 rounded-lg px-3 py-3 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button></div>
        </div>
    </template>
    <button type="button" x-cloak x-show="nameservers.length < {{ $maxNameservers }}" @click="nameservers.push('')" class="text-xs font-bold text-blue-600">+ افزودن نیم‌سرور دیگر</button>
    <noscript>@foreach($serverInputs as $nameserver)<div><label for="ns-fallback-{{ $loop->index }}" class="domain-label">نیم‌سرور {{ $loop->iteration }}</label><input id="ns-fallback-{{ $loop->index }}" name="nameservers[]" type="text" dir="ltr" value="{{ $nameserver }}" required maxlength="253" class="domain-input" autocapitalize="none" spellcheck="false"></div>@endforeach</noscript>
</fieldset>
