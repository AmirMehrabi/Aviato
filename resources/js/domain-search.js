export function domainSearch(config) {
    return {
        domain: config.domain || '',
        result: config.result || null,
        error: config.error || '',
        loading: false,
        get alternatives() {
            if (!this.result) return [];
            const label = this.result.domain.split('.')[0];
            return ['com', 'net', 'org', 'online'].map((tld) => `${label}.${tld}`).filter((name) => name !== this.result.domain);
        },
        async search(name = null) {
            if (this.loading) return;
            if (name !== null) this.domain = name;
            if (!this.domain.trim()) {
                this.error = 'نام دامنه را وارد کنید.';
                this.$refs.domain.focus();
                return;
            }
            this.loading = true;
            this.error = '';
            this.result = null;
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
                    const messages = {
                        401: 'نشست شما پایان یافته است. دوباره وارد حساب شوید.',
                        403: 'دسترسی دامنه برای حساب شما فعال نیست.',
                        419: 'نشست شما پایان یافته است. صفحه را تازه کنید و دوباره تلاش کنید.',
                        409: 'فضای کاری تغییر کرده است. صفحه را تازه کنید و دوباره ادامه دهید.',
                        429: 'تعداد جستجوها زیاد است. کمی صبر کنید و دوباره تلاش کنید.',
                    };
                    this.error = messages[response.status] || data.errors?.domain?.[0] || 'در حال حاضر بررسی دامنه امکان‌پذیر نیست. کمی بعد دوباره تلاش کنید.';
                    return;
                }
                if (typeof data.data?.available !== 'boolean') throw new Error('Invalid response');
                this.result = data.data;
                this.domain = this.result.domain;
                this.$nextTick(() => this.$refs.result.focus());
            } catch {
                this.error = 'ارتباط برقرار نشد. اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.';
            } finally {
                clearTimeout(timeout);
                this.loading = false;
            }
        },
    };
}
