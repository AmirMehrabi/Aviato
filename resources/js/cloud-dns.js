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
        values: [{ value: '' }], editing: false, busy: false, notice: '', error: '', deleting: null,
        get meta() { return this.types[this.type] || this.types.A; },
        get filtered() {
            const query = this.search.trim().toLowerCase();
            return this.records.filter(r => (!this.filter || r.type === this.filter) && `${r.name} ${r.type} ${r.records.join(' ')}`.toLowerCase().includes(query)).sort((a, b) => (this.ascending ? 1 : -1) * a[this.sort].localeCompare(b[this.sort]));
        },
        get fqdn() { return this.name === '@' ? this.zone : this.name.endsWith('.') ? this.name : `${this.name}.${this.zone}`; },
        shortName(name) { return name === `${this.zone}.` ? '@' : name.endsWith(`.${this.zone}.`) ? name.slice(0, -this.zone.length - 2) : name; },
        blank() { return { value: '', priority: '10', weight: '0', port: '', flags: '0', tag: 'issue' }; },
        defaultName(type) { return { A: '@', AAAA: '@', CNAME: 'www', MX: '@', TXT: '@', CAA: '@', SRV: '_sip._tcp', NS: 'sub' }[type]; },
        changeType() {
            this.values = [this.blank()]; this.error = '';
            if (!this.name || this.name === this.defaultName(this.previousType)) this.name = this.defaultName(this.type);
            this.previousType = this.type;
        },
        sortBy(key) { this.ascending = this.sort === key ? !this.ascending : true; this.sort = key; },
        typeClass(type) {
            return { A: 'bg-blue-100 text-blue-800', AAAA: 'bg-indigo-100 text-indigo-800', CNAME: 'bg-violet-100 text-violet-800', MX: 'bg-amber-100 text-amber-800', TXT: 'bg-slate-100 text-slate-800', CAA: 'bg-emerald-100 text-emerald-800', SRV: 'bg-cyan-100 text-cyan-800', NS: 'bg-orange-100 text-orange-800' }[type];
        },
        ttlLabel(ttl) { return ttl % 3600 === 0 ? `${ttl / 3600} ساعت` : ttl % 60 === 0 ? `${ttl / 60} دقیقه` : `${ttl} ثانیه`; },
        openEditor(record = null) {
            this.editing = !!record; this.error = ''; this.busy = false;
            this.type = record?.type || 'A'; this.previousType = this.type; this.name = record ? this.shortName(record.name) : '@'; this.ttl = record?.ttl || 300;
            this.values = record ? record.records.map(v => parseValue(record.type, v)) : [this.blank()];
            this.$refs.editor.showModal();
        },
        init() {
            if (this.old?.zone_file) this.$nextTick(() => this.$refs.importer?.showModal());
            if (this.old?.type && this.types[this.old.type]) {
                this.$nextTick(() => {
                    this.openEditor(); this.type = this.old.type; this.previousType = this.type; this.name = this.old.name || '@'; this.ttl = this.old.ttl || 300;
                    this.editing = this.old.editor_mode === 'edit';
                    this.values = (this.old.content || '').split('\n').map(v => parseValue(this.type, v));
                    this.error = this.validation || '';
                });
            }
        },
        save(event) {
            this.error = '';
            if (this.values.some(v => Object.values(v).some(s => /[\r\n]/.test(String(s))))) { this.error = 'هر مقدار باید یک رکورد مستقل باشد. برای رکورد بعدی از «افزودن مقدار» استفاده کنید.'; return; }
            if (this.type === 'CAA' && this.values.some(v => /["\\]/.test(v.value))) { this.error = 'مقدار CAA را بدون نقل‌قول یا بک‌اسلش وارد کنید.'; return; }
            const full = `${this.fqdn.replace(/\.$/, '')}.`.toLowerCase();
            if (!this.editing && this.records.some(r => r.name === full && r.type === this.type)) { this.error = 'این نام و نوع از قبل وجود دارد. برای افزودن مقدار، همان ردیف را ویرایش کنید.'; return; }
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
