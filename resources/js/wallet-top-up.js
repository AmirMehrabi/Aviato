export function walletTopUp(config) {
    return {
        amount: '',
        customAmount: '',
        selectedPreset: null,
        gateway: config.initialGateway || '',
        code: config.initialCode || '',
        checkedCode: '',
        promotion: null,
        checking: false,
        codeError: '',
        requestVersion: 0,
        submitting: false,
        init() {
            const initialAmount = this.normalizeDigits(config.initialAmount || '');
            if (initialAmount) {
                this.amount = initialAmount;
                const numericAmount = Number(initialAmount);
                if (config.presets.includes(numericAmount)) {
                    this.selectedPreset = numericAmount;
                } else {
                    this.customAmount = initialAmount;
                }
            }
            this.$nextTick(() => {
                const code = sessionStorage.getItem('aviato.gift_card_code');
                if (!code || !this.$refs.promotionCode) return;
                this.setCode(code);
                this.checkCode();
                this.$refs.promotionCode.scrollIntoView({ behavior: 'smooth', block: 'center' });
                this.$refs.promotionCode.focus();
                sessionStorage.removeItem('aviato.gift_card_code');
                sessionStorage.removeItem('aviato.gift_card_type');
            });
            if (config.focusTopUp) {
                this.$nextTick(() => this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        },
        setCode(value) {
            this.code = value;
            this.requestVersion++;
            this.checking = false;
            this.promotion = null;
            this.checkedCode = '';
            this.codeError = '';
        },
        removeCode() {
            this.setCode('');
            this.$nextTick(() => this.$refs.promotionCode.focus());
        },
        async checkCode() {
            if (!this.hasCode || this.checking || this.submitting) return;
            const code = this.code;
            const version = ++this.requestVersion;
            this.checking = true;
            this.promotion = null;
            this.checkedCode = '';
            this.codeError = '';
            try {
                const response = await fetch(config.previewUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ code, project_id: config.projectId }),
                });
                const data = await response.json();
                if (version !== this.requestVersion) return;
                if (!response.ok) {
                    this.codeError = data.errors?.code?.[0] || data.errors?.project_id?.[0]
                        || (response.status === 429 ? data.message : 'بررسی کد ممکن نشد. صفحه را تازه‌سازی کنید یا دوباره تلاش کنید.');
                    return;
                }
                this.promotion = data.promotion;
                this.checkedCode = code;
            } catch (error) {
                if (version === this.requestVersion) this.codeError = 'ارتباط برقرار نشد. کد و مبلغ شما حفظ شده‌اند؛ دوباره بررسی کنید.';
            } finally {
                if (version === this.requestVersion) this.checking = false;
            }
        },
        normalizeDigits(value) {
            const digits = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4',
                '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
                '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
            };
            return String(value).replace(/[۰-۹٠-٩]/g, digit => digits[digit]).replace(/[^\d]/g, '').replace(/^0+/, '');
        },
        selectPreset(value) {
            this.selectedPreset = value;
            this.customAmount = '';
            this.amount = String(value);
        },
        enterCustomAmount(value) {
            this.selectedPreset = null;
            this.customAmount = this.normalizeDigits(value);
            this.amount = this.customAmount;
            this.$nextTick(() => { this.$refs.customAmount.value = this.formattedCustomAmount; });
        },
        clearAmount() {
            this.amount = '';
            this.customAmount = '';
            this.selectedPreset = null;
            this.$nextTick(() => this.$refs.customAmount?.focus());
        },
        formatAmount(value) {
            return value ? new Intl.NumberFormat('fa-IR').format(Number(value)) : 'مبلغی انتخاب نشده';
        },
        formatRial(value) {
            return `${new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 1 }).format(Number(value) / 10)} تومان`;
        },
        get hasCode() { return this.code.trim().length > 0; },
        get codeChecked() { return this.promotion !== null && this.checkedCode === this.code; },
        get isInstant() { return this.codeChecked && !this.promotion.requires_payment; },
        get minimumMet() {
            return !this.codeChecked || this.promotion.type !== 'top_up_percentage'
                || Number(this.amount) * 10 >= this.promotion.minimum_top_up;
        },
        get bonusAmount() {
            if (!this.codeChecked || !this.minimumMet) return 0;
            if (this.promotion.type === 'top_up_percentage') {
                return Math.min(Math.floor(Number(this.amount) * 10 * this.promotion.percentage / 100), this.promotion.maximum_bonus);
            }
            return this.promotion.credit_amount;
        },
        get benefitTitle() {
            if (!this.codeChecked) return '';
            return this.promotion.type === 'top_up_percentage'
                ? `${new Intl.NumberFormat('fa-IR').format(this.promotion.percentage)}٪ اعتبار اضافه هنگام شارژ`
                : `${this.formatRial(this.promotion.credit_amount)} اعتبار هدیه`;
        },
        get formattedCustomAmount() { return this.customAmount ? this.formatAmount(this.customAmount) : ''; },
        get amountLabel() { return this.amount ? `${this.formatAmount(this.amount)} تومان` : 'مبلغی انتخاب نشده'; },
        get canSubmit() {
            const amount = Number(this.amount);
            return !this.submitting && !this.checking && !this.isInstant && amount >= 250000 && amount <= 50000000
                && Boolean(this.gateway) && (!this.hasCode || (this.codeChecked && this.minimumMet));
        },
        get canRedeem() { return this.isInstant && !this.checking && !this.submitting; },
        get submitLabel() {
            if (this.submitting) return 'در حال انتقال به درگاه…';
            if (this.checking) return 'در حال بررسی کد…';
            if (this.hasCode && !this.codeChecked) return 'ابتدا کد را بررسی یا حذف کنید';
            if (!this.minimumMet) return 'حداقل مبلغ هدیه را انتخاب کنید';
            if (!this.amount) return 'ابتدا مبلغ را انتخاب کنید';
            if (!this.canSubmit) return 'مبلغ و درگاه پرداخت را بررسی کنید';
            return this.codeChecked ? 'پرداخت و دریافت اعتبار' : 'پرداخت و افزایش موجودی';
        },
        submitPayment(event) {
            if (!this.canSubmit) { event.preventDefault(); return; }
            this.submitting = true;
        },
        submitGift(event) {
            if (!this.canRedeem) { event.preventDefault(); return; }
            this.submitting = true;
        },
    };
}
