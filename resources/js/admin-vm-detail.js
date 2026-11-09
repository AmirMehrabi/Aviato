export function adminVmDetail() {
    return {
        root: null, confirmAction: null, tab: 'overview', status: '', locked: false, stale: false,
        provisioning: '', operation: '', pollMessage: '', loading: false, timer: null, controller: null,
        get provisioningLabel() { return ({ready:'آماده',pending:'در حال راه‌اندازی',failed:'ناموفق'})[this.provisioning] || this.provisioning; },
        get statusLabel() { return ({running:'روشن',stopped:'خاموش',suspended:'تعلیق',deleting:'در حال حذف',deleted:'حذف شده'})[this.status] || this.status; },
        init() {
            this.root = this.$el;
            this.tab = this.root.dataset.initialTab;
            const hasErrors = this.root.dataset.validationErrors === '1';
            this.status = this.root.dataset.status;
            this.provisioning = this.root.dataset.provisioning;
            this.locked = this.root.dataset.locked === '1';
            this.readHash = () => {
                const key = location.hash.slice(1);
                if (['overview','configuration','billing','activities','management'].includes(key)) this.tab = key === 'billing' && this.root.dataset.canBilling !== '1' ? 'overview' : key;
                if (key === 'upgrade-history') this.tab = 'activities';
            };
            if (!hasErrors) this.readHash();
            window.addEventListener('hashchange', this.readHash);
            this.$watch('tab', () => this.$nextTick(() => this.root.querySelector('[role=tab][aria-selected=true]')?.scrollIntoView({block:'nearest', inline:'nearest'})));
            this.$watch('confirmAction', value => {
                if (value) this.$nextTick(() => {
                    if (this.$refs.confirmation.open) return;
                    this.$refs.confirmation.showModal();
                    const cancel = [...this.$refs.confirmation.querySelectorAll('button')].find(button => button.textContent.trim() === 'انصراف');
                    cancel?.focus();
                });
                else this.$refs.confirmation.close();
            });
            this.poll();
        },
        schedule() { this.timer = setTimeout(() => this.poll(), 15000); },
        async poll() {
            clearTimeout(this.timer);
            if (this.loading) return;
            if (document.hidden) { this.schedule(); return; }
            this.loading = true;
            this.controller = new AbortController();
            const timeout = setTimeout(() => this.controller.abort(), 10000);
            try {
                const url = new URL(location.href); url.search = '?status_only=1'; url.hash = '';
                const response = await fetch(url, { headers: {Accept:'application/json'}, cache:'no-store', signal:this.controller.signal });
                if (!response.ok || response.redirected) throw new Error('status');
                const data = await response.json();
                this.stale = this.stale || data.status !== this.root.dataset.status || data.provisioning !== this.root.dataset.provisioning || String(data.generation) !== this.root.dataset.generation || data.locked !== (this.root.dataset.locked === '1');
                this.provisioning = data.provisioning; this.status = data.status; this.locked = data.locked; this.operation = data.operation;
                this.pollMessage = 'آخرین بررسی: ' + new Date().toLocaleTimeString('fa-IR');
            } catch (error) { this.pollMessage = 'بررسی وضعیت ممکن نشد؛ دوباره تلاش کنید.'; }
            finally { clearTimeout(timeout); this.loading = false; this.schedule(); }
        },
        trapDialogFocus(event) {
            const controls = [...this.$refs.confirmation.querySelectorAll('button, input, a[href], select, textarea, [tabindex]')]
                .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
            if (!controls.length) return;
            const current = controls.indexOf(document.activeElement);
            controls[(current + (event.shiftKey ? -1 : 1) + controls.length) % controls.length].focus();
        },
        navigateTabs(event) {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            const tabs = [...event.currentTarget.querySelectorAll('[role=tab]')];
            const current = tabs.indexOf(event.target);
            if (current < 0) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (current + (event.key === 'ArrowLeft' ? 1 : -1) + tabs.length) % tabs.length;
            tabs[next].click();
            tabs[next].focus();
        },
        guardSubmission(event) {
            const form = event.target;
            if (form.method.toLowerCase() === 'get') return;
            if (this.stale || form.dataset.submitting) {
                event.preventDefault();
                this.pollMessage = this.stale ? 'وضعیت سرور تغییر کرده است؛ ابتدا صفحه را تازه کنید.' : 'درخواست در حال ارسال است.';
                return;
            }
            form.dataset.submitting = '1';
            this.$nextTick(() => form.querySelectorAll('button[type=submit]').forEach(button => button.disabled = true));
        },
        async copy(value) {
            try { await navigator.clipboard.writeText(value); this.pollMessage = 'کپی شد'; }
            catch { this.pollMessage = 'کپی ممکن نشد؛ مقدار را انتخاب و کپی کنید.'; }
        },
        destroy() { clearTimeout(this.timer); this.controller?.abort(); window.removeEventListener('hashchange', this.readHash); }
    };
}
