<form method="POST" action="{{ route('customer.servers.restart', $server, false) }}"
    x-data="{ confirming: false, submitting: false }" @submit.prevent="if (confirming) { submitting = true; $el.submit(); } else confirming = true">
    @csrf
    <input type="hidden" name="power_generation" value="{{ (int) data_get($server->desired_state, 'power_generation', 0) }}">
    <button type="submit" :disabled="submitting" class="{{ $actionClass }}">
        <svg class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8m0-5v5h5"/></svg>
        <span x-text="confirming ? 'برای تأیید دوباره بزنید' : 'راه‌اندازی مجدد'">راه‌اندازی مجدد</span>
    </button>
</form>
