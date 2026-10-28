<div class="mb-5 rounded-xl border border-slate-200 bg-white p-5 text-sm space-y-2">
    <p class="font-bold">صورتحساب برای: {{ $recipient['name'] ?? '—' }} · {{ ($recipient['type'] ?? '') === 'corporate' ? 'حقوقی' : 'حقیقی' }}</p>
    @foreach(['national_id' => 'شناسه ملی', 'national_code' => 'کد ملی', 'register_number' => 'شماره ثبت', 'address' => 'نشانی', 'postal_code' => 'کد پستی', 'tax_number' => 'شماره اقتصادی', 'email' => 'ایمیل'] as $field => $label)
        @if(filled($recipient[$field] ?? null))<p>{{ $label }}: {{ $recipient[$field] }}</p>@endif
    @endforeach
</div>
