export function initTicketVmPickers() {
    document.querySelectorAll('[data-ticket-machine-picker][data-url]').forEach((picker) => {
        if (picker.dataset.vmPickerReady) return;
        picker.dataset.vmPickerReady = 'true';

        const customer = picker.closest('form').querySelector('[data-ticket-customer]');
        const select = picker.querySelector('select');
        const status = picker.querySelector('[data-vm-status]');
        const retry = picker.querySelector('[data-vm-retry]');
        let activeRequest;

        async function loadMachines() {
            activeRequest?.abort();
            const request = new AbortController();
            activeRequest = request;
            const hadSelection = select.value !== '';
            select.replaceChildren(new Option('بدون ماشین مجازی', ''));
            select.disabled = true;
            retry.hidden = true;
            select.removeAttribute('aria-invalid');

            if (!customer.value) {
                select.setAttribute('aria-busy', 'false');
                status.textContent = 'ابتدا مشتری را انتخاب کنید تا ماشین‌های مجازی او نمایش داده شوند.';
                return;
            }

            select.setAttribute('aria-busy', 'true');
            status.textContent = 'در حال دریافت ماشین‌های مجازی مشتری…';
            const timeout = setTimeout(() => request.abort(), 15000);

            try {
                const url = new URL(picker.dataset.url, window.location.origin);
                url.searchParams.set('customer_id', customer.value);
                const response = await fetch(url, {
                    signal: request.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (response.status === 467) throw new Error('Access verification required');
                if (!response.ok) throw new Error('Unable to load virtual machines');
                const data = await response.json();
                if (!Array.isArray(data.virtual_machines)) throw new Error('Invalid response');
                if (activeRequest !== request) return;

                data.virtual_machines.forEach((vm) => {
                    const option = new Option(vm.label, String(vm.id));
                    option.dir = 'ltr';
                    select.add(option);
                });
                select.disabled = data.virtual_machines.length === 0;
                status.textContent = data.virtual_machines.length === 0
                    ? 'این مشتری ماشین مجازی قابل انتخابی ندارد. می‌توانید تیکت را بدون ماشین مجازی ثبت کنید.'
                    : (hadSelection ? 'انتخاب قبلی پاک شد. در صورت نیاز، ماشین مجازی مشتری جدید را انتخاب کنید.' : 'ماشین‌های مجازی مشتری آماده انتخاب هستند. انتخاب اختیاری است.');
            } catch (error) {
                if (activeRequest !== request) return;
                status.textContent = error.message === 'Access verification required'
                    ? 'برای دریافت ماشین‌های مجازی، تأیید دسترسی سایت لازم است. پس از تأیید دسترسی در یک زبانه دیگر، دوباره تلاش کنید؛ متن تیکت در این صفحه حفظ می‌شود.'
                    : 'دریافت ماشین‌های مجازی انجام نشد. دوباره تلاش کنید یا تیکت را بدون ماشین مجازی ثبت کنید.';
                retry.hidden = false;
            } finally {
                clearTimeout(timeout);
                if (activeRequest === request) select.setAttribute('aria-busy', 'false');
            }
        }

        customer.addEventListener('change', loadMachines);
        retry.addEventListener('click', loadMachines);
    });
}
