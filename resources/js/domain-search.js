export function domainSearch(config) {
    return {
        domain: config.domain || '',
        result: config.result || null,
        error: config.error || '',
        loading: false,
        period: config.result?.retail?.periods?.[0] ?? null,
        comparing: false,
        comparison: [],
        comparisonKey: 0,
        get selectedPrice() {
            return this.result?.retail?.prices?.[this.period] || this.result?.retail;
        },
        continueUrl: null,
        cooldown: 0,
        cooldownTimer: null,
        init() {
            if (config.autoSearch && this.domain.trim() && !this.result) this.search();
        },
        destroy() {
            clearInterval(this.cooldownTimer);
        },
        pause(seconds) {
            this.cooldown = Math.max(1, Math.ceil(seconds));
            clearInterval(this.cooldownTimer);
            const until = Date.now() + this.cooldown * 1000;
            this.cooldownTimer = setInterval(() => {
                this.cooldown = Math.max(0, Math.ceil((until - Date.now()) / 1000));
                if (!this.cooldown) clearInterval(this.cooldownTimer);
            }, 1000);
        },
        clearResult() {
            this.result = null;
            this.continueUrl = null;
            this.comparisonKey++;
            this.comparing = false;
            this.comparison = [];
        },
        get alternatives() {
            if (!this.result) return [];
            const label = this.result.domain.split('.')[0];
            return ['com', 'net', 'org', 'online'].map((tld) => `${label}.${tld}`).filter((name) => name !== this.result.domain);
        },
        async lookup(name) {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 18000);
            try {
                const response = await fetch(config.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: controller.signal,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ domain: name, workspace: config.workspace }),
                });
                const data = await response.json().catch(() => ({}));
                return { response, data };
            } finally {
                clearTimeout(timeout);
            }
        },
        async compare() {
            if (this.comparing || this.cooldown) return;
            const key = ++this.comparisonKey;
            this.comparing = true;
            const candidates = this.alternatives.slice(0, 3);
            const results = await Promise.all(candidates.map(async (domain) => {
                try {
                    const { response, data } = await this.lookup(domain);
                    if (key !== this.comparisonKey) return { domain };
                    if (response.status === 429) this.pause(60);
                    if (response.ok && typeof data.data?.available === 'boolean') return data.data;
                } catch {
                    // A failed alternative should not hide results for other names.
                }
                return { domain };
            }));
            if (key !== this.comparisonKey) return;
            this.comparison = results;
            this.comparing = false;
        },
        selectResult(result) {
            this.result = result;
            this.period = result.retail?.periods?.[0] ?? null;
            this.domain = result.domain;
            this.error = '';
            this.$nextTick(() => {
                if (!config.scrollToResult) {
                    this.$refs.result?.focus();
                    return;
                }

                // Alpine shows the result after the reactive update.
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    const target = this.$refs.result;
                    if (!target || this.loading || this.result?.domain !== result.domain) return;
                    target.focus({ preventScroll: true });
                    target.scrollIntoView({ block: 'start' });
                }));
            });
        },
        async search(name = null) {
            if (this.loading) return;
            if (this.cooldown) {
                this.error = `لطفاً ${this.cooldown} ثانیه صبر کنید و دوباره جستجو کنید.`;
                return;
            }
            if (name !== null) this.domain = name;
            if (!this.domain.trim()) {
                this.error = 'نام دامنه را وارد کنید.';
                this.$refs.domain.focus();
                return;
            }
            this.loading = true;
            this.error = '';
            this.clearResult();
            try {
                const { response, data } = await this.lookup(this.domain);
                if (!response.ok) {
                    if (response.status === 429) {
                        const retry = response.headers.get('Retry-After');
                        const seconds = /^\d+$/.test(retry || '') ? Number(retry) : Math.ceil((Date.parse(retry) - Date.now()) / 1000);
                        this.pause(Number.isFinite(seconds) && seconds > 0 ? seconds : (data.retry_after || 60));
                    }
                    const messages = {
                        401: 'نشست شما پایان یافته است. دوباره وارد حساب شوید.',
                        403: 'دسترسی دامنه برای حساب شما فعال نیست.',
                        419: 'نشست شما پایان یافته است. صفحه را تازه کنید و دوباره تلاش کنید.',
                        409: 'فضای کاری تغییر کرده است. صفحه را تازه کنید و دوباره ادامه دهید.',
                        429: 'تعداد جستجوها زیاد است. کمی صبر کنید و دوباره تلاش کنید.',
                    };
                    this.error = (response.status === 503 ? data.message : messages[response.status]) || data.errors?.domain?.[0] || 'در حال حاضر بررسی دامنه امکان‌پذیر نیست. کمی بعد دوباره تلاش کنید.';
                    return;
                }
                if (typeof data.data?.available !== 'boolean') throw new Error('Invalid response');
                this.selectResult(data.data);
                this.continueUrl = config.publicSearch ? (data.continue_url || null) : null;
            } catch {
                this.error = 'ارتباط برقرار نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';
            } finally {
                this.loading = false;
            }
        },
    };
}
