<div class="mt-4" data-ticket-machine-picker @if($dynamic) data-url="{{ route('admin.tickets.virtual-machines', [], false) }}" @endif>
    <label for="ticket-virtual-machine" class="text-sm font-black text-slate-700">ماشین مجازی مرتبط <span class="font-normal text-slate-500">(اختیاری)</span></label>
    <select id="ticket-virtual-machine" name="virtual_machine_id" aria-describedby="ticket-vm-help ticket-vm-status" @disabled(! $hasCustomer || $virtualMachines->isEmpty()) @error('virtual_machine_id') aria-invalid="true" @enderror class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold focus:border-[#0069FF] focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50 disabled:text-slate-500">
        <option value="">بدون ماشین مجازی</option>
        @foreach($virtualMachines as $vm)
            <option value="{{ $vm->id }}" @selected((string) old('virtual_machine_id') === (string) $vm->id) dir="ltr">{{ $vm->display_name }}{{ $vm->ip_address ? ' — '.$vm->ip_address : '' }}</option>
        @endforeach
    </select>
    <p id="ticket-vm-help" class="mt-2 text-xs leading-6 text-slate-500">اگر درخواست درباره یک ماشین مجازی است، آن را انتخاب کنید تا پشتیبانی سریع‌تر بررسی کند. برای سایر درخواست‌ها، انتخاب لازم نیست.</p>
    <p id="ticket-vm-status" data-vm-status role="status" aria-live="polite" class="mt-1 text-xs leading-6 text-slate-600">{{ ! $hasCustomer ? 'ابتدا مشتری را انتخاب کنید تا ماشین‌های مجازی او نمایش داده شوند.' : ($virtualMachines->isEmpty() ? 'ماشین مجازی قابل انتخابی وجود ندارد. می‌توانید تیکت را بدون ماشین مجازی ثبت کنید.' : '') }}</p>
    <button type="button" data-vm-retry hidden class="mt-2 rounded-lg border border-blue-200 px-3 py-2 text-xs font-bold text-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">تلاش دوباره</button>
    @error('virtual_machine_id')
        <p class="mt-2 text-xs font-bold text-red-600">{{ $message }}</p>
    @enderror
</div>

@if($dynamic)
    @once
        <script type="module">
            {!! file_get_contents(resource_path('js/ticket-vm-picker.js')) !!}

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initTicketVmPickers, { once: true });
            } else {
                initTicketVmPickers();
            }
        </script>
    @endonce
@endif
