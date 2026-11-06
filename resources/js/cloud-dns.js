export const recordTypes = {
    A: { title: 'اتصال به IPv4', help: 'نام دامنه را به آدرس IPv4 سرور وصل کنید.', fields: [{ key: 'value', label: 'آدرس IPv4', placeholder: '203.0.113.10' }], example: 'www → 203.0.113.10', name: '@ یا www' },
    AAAA: { title: 'اتصال به IPv6', help: 'برای سرورهایی که آدرس IPv6 دارند.', fields: [{ key: 'value', label: 'آدرس IPv6', placeholder: '2001:db8::10' }], example: 'www → 2001:db8::10', name: '@ یا www' },
    CNAME: { title: 'نام مستعار', help: 'یک زیردامنه را به نام دامنه دیگری وصل کنید. در ریشه (@) مجاز نیست و در همین نام نباید رکورد دیگری وجود داشته باشد.', fields: [{ key: 'value', label: 'دامنه مقصد', placeholder: 'app.example.com' }], example: 'www → app.example.com', name: 'www' },
    MX: { title: 'سرور ایمیل', help: 'مقصد دریافت ایمیل را مشخص کنید. عدد اولویت کمتر، زودتر انتخاب می‌شود.', fields: [{ key: 'priority', label: 'اولویت', placeholder: '10', number: true }, { key: 'value', label: 'سرور ایمیل', placeholder: 'mail.example.com' }], example: '@ → 10 mail.example.com', name: '@' },
    TXT: { title: 'متن و تأیید سرویس', help: 'متن تأیید مالکیت، SPF یا DKIM را عیناً وارد کنید. برای چند رکورد مستقل، «افزودن مقدار» را بزنید.', fields: [{ key: 'value', label: 'محتوای متنی', placeholder: 'v=spf1 include:_spf.example.com ~all', multiline: true }], example: '@ → v=spf1 include:_spf.example.com ~all', name: '@ یا _dmarc یا selector._domainkey' },
    CAA: { title: 'مجوز صدور گواهی SSL', help: 'مشخص کنید کدام مرجع می‌تواند گواهی صادر کند. issue برای گواهی معمولی و issuewild برای گواهی wildcard است.', fields: [{ key: 'flags', label: 'پرچم', placeholder: '0', number: true, max: 255 }, { key: 'tag', label: 'برچسب', placeholder: 'issue' }, { key: 'value', label: 'مرجع یا مقدار', placeholder: 'letsencrypt.org' }], example: '@ → 0 issue "letsencrypt.org"', name: '@' },
    SRV: { title: 'مقصد سرویس', help: 'نام را به شکل _service._protocol بنویسید. اولویت کمتر مقدم است؛ وزن، توزیع بین مقصدهای هم‌اولویت را تعیین می‌کند.', fields: [{ key: 'priority', label: 'اولویت', placeholder: '10', number: true }, { key: 'weight', label: 'وزن', placeholder: '5', number: true }, { key: 'port', label: 'پورت', placeholder: '5060', number: true }, { key: 'value', label: 'سرور مقصد', placeholder: 'sip.example.com' }], example: '_sip._tcp → 10 5 5060 sip.example.com', name: '_sip._tcp' },
    NS: { title: 'واگذاری زیردامنه', help: 'مدیریت یک زیردامنه را به نیم‌سرور دیگری بسپارید. نیم‌سرورهای اصلی دامنه از این جدول تغییر نمی‌کنند.', fields: [{ key: 'value', label: 'نیم‌سرور مقصد', placeholder: 'ns1.example.com' }], example: 'sub → ns1.example.com', name: 'sub' },
};

export function parseValue(type, content) {
    const parts = content.trim().split(/\s+/);
    if (type === 'MX') return { priority: parts.shift(), value: parts.join(' ') };
    if (type === 'SRV') return { priority: parts.shift(), weight: parts.shift(), port: parts.shift(), value: parts.join(' ') };
    if (type === 'CAA') {
        const match = content.match(/^(\d+)\s+(\S+)\s+"(.*)"$/);
        if (match) return { flags: match[1], tag: match[2], value: match[3] };
    }
    return { value: content };
}

export function serializeValues(type, values) {
    return values.map(v => {
        if (type === 'MX') return `${v.priority} ${v.value}`;
        if (type === 'SRV') return `${v.priority} ${v.weight} ${v.port} ${v.value}`;
        if (type === 'CAA') return `${v.flags} ${v.tag} "${v.value}"`;
        return v.value;
    }).join('\n');
}

export function cloudDns(options) {
    return {
        ...options, types: recordTypes, search: '', filter: '', type: 'A', name: '@', ttl: 300, sort: 'type', ascending: true, previousType: 'A',
        connection: options.connection || {}, recordsStale: false, pollTimer: null, statusError: '', refreshing: false, recordsReviewed: false, emptyDomain: false, dnssecReviewed: false, importText: '', importPreview: null, previewBusy: false, importError: '', fileError: '', values: [{ value: '' }], editorOpen: false, editorInline: false, editingRecord: null, ttlChoice: '300', editing: false, busy: false, notice: '', error: '', deleting: null,
        get meta() { return this.types[this.type] || this.types.A; },
        get filtered() {
            const query = this.search.trim().toLowerCase();
            return this.records.filter(r => (!this.filter || r.type === this.filter) && `${r.name} ${r.type} ${r.records.join(' ')}`.toLowerCase().includes(query)).sort((a, b) => (this.ascending ? 1 : -1) * a[this.sort].localeCompare(b[this.sort]));
        },
        get fqdn() { const name = this.name.trim().toLowerCase(); return name === '@' ? this.zone : name.endsWith('.') || name === this.zone || name.endsWith(`.${this.zone}`) ? name : `${name}.${this.zone}`; },
        get migrationReviewed() { return !this.recordsStale && (this.recordsReviewed || this.emptyDomain) && this.dnssecReviewed; },
        shortName(name) { return name === `${this.zone}.` ? '@' : name.endsWith(`.${this.zone}.`) ? name.slice(0, -this.zone.length - 2) : name; },
        blank() { return { value: '', priority: '10', weight: '0', port: '', flags: '0', tag: 'issue' }; },
        defaultName(type) { return { A: '@', AAAA: '@', CNAME: 'www', MX: '@', TXT: '@', CAA: '@', SRV: '_sip._tcp', NS: 'sub' }[type]; },
        changeType() {
            this.values = [this.blank()]; this.error = '';
            if (!this.name || this.name === this.defaultName(this.previousType)) this.name = this.defaultName(this.type);
            this.previousType = this.type;
        },
        sortBy(key) { this.parkEditor(); this.ascending = this.sort === key ? !this.ascending : true; this.sort = key; },
        typeClass(type) {
            return { A: 'bg-blue-600 text-white', AAAA: 'bg-indigo-600 text-white', CNAME: 'bg-violet-600 text-white', MX: 'bg-amber-500 text-slate-950', TXT: 'bg-slate-700 text-white', CAA: 'bg-emerald-600 text-white', SRV: 'bg-cyan-700 text-white', NS: 'bg-orange-600 text-white' }[type];
        },
        ttlLabel(ttl) { return ttl % 3600 === 0 ? `${ttl / 3600} ساعت` : ttl % 60 === 0 ? `${ttl / 60} دقیقه` : `${ttl} ثانیه`; },
        openEditor(record = null) {
            this.editing = !!record; this.error = ''; this.fieldErrors = {}; this.busy = false;
            this.type = record?.type || 'A'; this.previousType = this.type; this.name = record ? this.shortName(record.name) : '@'; this.ttl = record?.ttl || 300;
            this.values = record ? record.records.map(v => parseValue(record.type, v)) : [this.blank()];
            this.editingRecord = record;
            this.ttlChoice = [300, 600, 1800, 3600, 86400].includes(Number(this.ttl)) ? String(this.ttl) : 'custom';
            this.editorTrigger = document.activeElement;
            this.editorOpen = true;
            this.$nextTick(() => { this.placeEditor(); this.$refs.editor.querySelector('input:not([type="hidden"]):not([readonly]), textarea')?.focus(); });
        },
        // Move the shared form before filtering or sorting can remove its row.
        parkEditor() {
            if (!this.editorOpen || !this.editorInline) return;
            this.$refs.editor.close();
            window.Alpine.mutateDom(() => this.$refs.editorHome.append(this.$refs.editor));
        },
        placeEditor() {
            if (!this.editorOpen) return;
            const editor = this.$refs.editor;
            const inline = window.matchMedia('(min-width: 640px)').matches;
            const host = inline ? (this.editingRecord && this.filtered.some(r => r.id === this.editingRecord.id)
                ? document.getElementById(`dns-editor-host-${this.editingRecord.id}`) : this.$refs.editorTop) : this.$refs.editorHome;
            if (!host) return;
            if (editor.parentElement !== host || this.editorInline !== inline) {
                if (editor.open) editor.close();
                window.Alpine.mutateDom(() => host.append(editor));
                this.editorInline = inline;
            }
            if (!editor.open) {
                if (inline) editor.show();
                else editor.showModal();
            }
        },
        closeEditor() {
            this.$refs.editor.close();
            this.editorOpen = false;
            window.Alpine.mutateDom(() => this.$refs.editorHome.append(this.$refs.editor));
            this.editorTrigger?.focus();
        },
        confirmDelete() {
            if (!this.editingRecord) return;
            this.deleting = this.editingRecord;
            this.closeEditor();
            this.$refs.deleter.showModal();
        },
        async refreshStatus() {
            if (!this.statusUrl || this.refreshing) return;
            this.refreshing = true;
            try {
                const response = await fetch(this.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error();
                const connection = await response.json();
                if (connection.revision !== this.connection.revision) { this.recordsStale = true; this.recordsReviewed = false; this.emptyDomain = false; }
                this.connection = connection; this.statusError = '';
            } catch { this.statusError = 'دریافت وضعیت تازه ممکن نشد؛ وضعیت نمایش‌داده‌شده مربوط به آخرین دریافت است.'; }
            finally { this.refreshing = false; }
        },
        async previewImport() {
            this.previewBusy = true; this.importPreview = null; this.importError = '';
            const text = this.importText;
            try {
                const response = await fetch(this.previewUrl, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.$refs.importToken.value }, body: JSON.stringify({ zone_file: text }) });
                const report = await response.json();
                if (!response.ok) throw new Error(Object.values(report.errors || {}).flat().join(' ') || 'بررسی فایل ممکن نشد.');
                if (text === this.importText) this.importPreview = report;
            } catch (error) { this.importError = error.message || 'ارتباط برقرار نشد؛ دوباره تلاش کنید.'; }
            finally { this.previewBusy = false; }
        },
        async loadZoneFile(event) {
            this.fileError = ''; this.importPreview = null;
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 250000) { this.fileError = 'حجم فایل باید کمتر از ۲۵۰ کیلوبایت باشد.'; return; }
            try { this.importText = await file.text(); }
            catch { this.fileError = 'خواندن فایل ممکن نشد؛ متن را کپی کنید.'; }
        },
        destroy() { if (this.pollTimer) clearInterval(this.pollTimer); },
        init() {
            this.importText = this.old?.zone_file || '';
            for (const key of ['search', 'filter', 'sort', 'ascending']) this.$watch(key, () => this.$nextTick(() => this.placeEditor()));
            if (this.statusUrl) this.pollTimer = setInterval(() => { if (!document.hidden && !this.connection.healthy) this.refreshStatus(); }, 15000);
            if (this.old?.zone_file) this.$nextTick(() => this.$refs.importer?.showModal());
            if (this.old?.type && this.types[this.old.type]) {
                this.$nextTick(() => {
                    this.openEditor(); this.type = this.old.type; this.previousType = this.type; this.name = this.old.name || '@'; this.ttl = this.old.ttl || 300;
                    this.editing = this.old.editor_mode === 'edit';
                    this.editingRecord = this.editing ? this.records.find(r => r.type === this.type && r.name.toLowerCase() === `${this.fqdn.replace(/\.$/, '')}.`.toLowerCase()) || null : null;
                    this.ttlChoice = [300, 600, 1800, 3600, 86400].includes(Number(this.ttl)) ? String(this.ttl) : 'custom';
                    this.$nextTick(() => this.placeEditor());
                    this.values = (this.old.content || '').split('\n').map(v => parseValue(this.type, v));
                    this.error = this.validation || '';
                    this.fieldErrors = options.fieldErrors || {};
                });
            }
        },
        save(event) {
            this.error = '';
            if (this.values.some(v => Object.values(v).some(s => /[\r\n]/.test(String(s))))) { this.error = 'هر مقدار باید یک رکورد مستقل باشد. برای رکورد بعدی از «افزودن مقدار» استفاده کنید.'; return; }
            if (this.type === 'CAA' && this.values.some(v => /["\\]/.test(v.value))) { this.error = 'مقدار CAA را بدون نقل‌قول یا بک‌اسلش وارد کنید.'; return; }
            if (this.type === 'NS' && this.fqdn.replace(/\.$/, '') === this.zone) { this.error = 'NS فقط برای واگذاری زیردامنه است. نیم‌سرورهای اصلی را در بخش نیم‌سرورهای دامنه ببینید.'; return; }
            const full = `${this.fqdn.replace(/\.$/, '')}.`.toLowerCase();
            const existing = this.records.find(r => r.name === full && r.type === this.type);
            if (!this.editing && existing && this.type === 'NS') {
                const additions = this.values; this.openEditor(existing);
                this.values.push(...additions.filter(v => v.value && !existing.records.some(value => value.replace(/\.$/, '').toLowerCase() === v.value.replace(/\.$/, '').toLowerCase())));
                this.notice = 'نیم‌سرورهای فعلی حفظ شدند؛ فهرست را مرور و ذخیره کنید.'; return;
            }
            if (!this.editing && existing) { this.error = 'این نام و نوع از قبل وجود دارد. برای افزودن مقدار، همان ردیف را ویرایش کنید.'; return; }
            this.$refs.content.value = serializeValues(this.type, this.values);
            this.busy = true; event.target.submit();
        },
        async copy(value) {
            try { await navigator.clipboard.writeText(value); this.notice = 'کپی شد'; }
            catch { this.notice = 'کپی خودکار ممکن نشد؛ متن را انتخاب و کپی کنید.'; }
            setTimeout(() => { this.notice = ''; }, 4000);
        },
    };
}
