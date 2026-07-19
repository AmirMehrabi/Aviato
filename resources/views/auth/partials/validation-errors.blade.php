@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold leading-7 text-red-700" role="alert" aria-live="polite">
        <p>لطفاً اطلاعات واردشده را بررسی کنید:</p>
        <ul class="mt-1 list-inside list-disc font-semibold">
            @foreach (collect($errors->all())->unique() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
