export function domainSearch(config) {
    return {
        domain: config.domain || '',
        result: config.result || null,
        error: config.error || '',
        loading: false,
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
        },
        get alternatives() {
            if (!this.result) return [];
            const label = this.result.domain.split('.')[0];
            return ['com', 'net', 'org', 'online'].map((tld) => `${label}.${tld}`).filter((name) => name !== this.result.domain);
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
                    body: JSON.stringify({ domain: this.domain, workspace: config.workspace }),
                });
                const data = await response.json().catch(() => ({}));
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
                this.result = data.data;
                this.domain = this.result.domain;
                this.continueUrl = config.publicSearch ? (data.continue_url || null) : null;
                this.$nextTick(() => this.$refs.result?.focus());
            } catch {
                this.error = 'ارتباط برقرار نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';
            } finally {
                clearTimeout(timeout);
                this.loading = false;
            }
        },
    };
}
