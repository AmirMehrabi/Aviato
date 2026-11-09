import RFB from '@novnc/novnc';

const keys = { Escape: 0xff1b, Tab: 0xff09, Enter: 0xff0d, Backspace: 0xff08, Delete: 0xffff, ArrowLeft: 0xff51, ArrowUp: 0xff52, ArrowRight: 0xff53, ArrowDown: 0xff54 };
const reasons = { unsupported: 'کنسول برای این نوع ماشین پشتیبانی نمی‌شود.', not_configured: 'اتصال نمایشگر این ماشین هنوز تنظیم نشده است. با پشتیبانی تماس بگیرید.', locked: 'کنسول هنگام حذف یا قفل بودن ماشین در دسترس نیست.', provisioning: 'پس از پایان راه‌اندازی ماشین، دوباره متصل شوید.' };
const modifiers = { Control: [0xffe3, 'ControlLeft'], Alt: [0xffe9, 'AltLeft'], Shift: [0xffe1, 'ShiftLeft'] };

export function vmConsole(config) {
    // Keep noVNC outside Alpine's reactive proxies.
    let rfb = null;
    let controller = null;
    let deadline = null;
    let retryTimer = null;
    let generation = 0;
    let savedOverflow = '';
    let focusListener, fullscreenListener, escapeListener;
    return {
        connected: false, loading: false, error: '', notice: '', stage: 'آماده',
        retryCount: 0, retryIn: 0, retryable: true, reference: '', errorCode: '',
        viewOnly: false, display: 'fit', expanded: false, fullscreen: false, focused: false,
        clipboard: '', receivedClipboard: '', panel: '', mobileText: '', drag: false,
        held: { Control: false, Alt: false, Shift: false }, pendingPower: null,
        functionKeys: Array.from({ length: 12 }, (_, index) => index + 1),
        get statusText() { return this.connected ? 'متصل' : this.loading ? this.stage : this.error ? 'اتصال ناموفق' : 'قطع اتصال'; },
        init() {
            focusListener = () => {
                this.focused = this.connected && this.$refs.screen.contains(document.activeElement);
            };
            fullscreenListener = () => { this.fullscreen = document.fullscreenElement === this.$refs.workspace; };
            escapeListener = (event) => {
                if (event.key === 'Escape' && event.ctrlKey && event.altKey && event.shiftKey) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    this.releaseKeys();
                    rfb?.blur();
                    this.$refs.keyboardButton.focus();
                } else if (event.key === 'Escape' && this.expanded && !this.focused) this.toggleExpanded();
            };
            document.addEventListener('focusin', focusListener);
            document.addEventListener('fullscreenchange', fullscreenListener);
            document.addEventListener('keydown', escapeListener, true);
            this.connect();
        },
        clearTimers() {
            clearTimeout(deadline);
            clearInterval(retryTimer);
            this.retryIn = 0;
        },
        closeTransport() {
            generation++;
            controller?.abort();
            controller = null;
            this.releaseKeys();
            const previous = rfb;
            rfb = null;
            previous?.disconnect();
            this.connected = false;
            this.focused = false;
        },
        async connect() {
            this.clearTimers();
            this.closeTransport();
            const attempt = generation;
            this.loading = true;
            this.error = '';
            this.notice = '';
            this.errorCode = '';
            this.reference = '';
            this.retryable = true;
            this.stage = 'در حال ایجاد نشست';
            controller = new AbortController();
            deadline = setTimeout(() => {
                if (attempt === generation) this.fail('پاسخ اتصال به‌موقع دریافت نشد.', 'timeout', true);
            }, 20000);
            try {
                const response = await fetch(config.sessionUrl, {
                    method: 'POST', signal: controller.signal, credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({}),
                });
                if (attempt !== generation) return;
                const session = await response.json().catch(() => ({}));
                if (attempt !== generation) return;
                this.reference = session.reference || '';
                if (!response.ok) {
                    const expired = [401, 419].includes(response.status);
                    const forbidden = response.status === 403;
                    this.fail(expired ? 'نشست پنل منقضی شده است. صفحه را تازه کنید و دوباره وارد شوید.' : forbidden ? 'اجازه دسترسی به کنسول را ندارید.' : reasons[session.code] || session.message || 'امکان ایجاد نشست وجود ندارد.', expired ? 'portal_session' : session.code || `http_${response.status}`, !expired && !forbidden && (response.status >= 500 || response.status === 429 || session.retryable === true));
                    return;
                }
                this.stage = 'در حال اتصال به نمایشگر';
                const url = new URL(session.websocket_url, window.location.href);
                if (!['https:', 'http:', 'ws:', 'wss:'].includes(url.protocol) || url.host !== window.location.host) throw new Error('invalid_transport');
                url.protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const client = new RFB(this.$refs.screen, url.href, { shared: true, credentials: { password: session.password || '' } });
                rfb = client;
                client.viewOnly = this.viewOnly;
                client.resizeSession = true;
                this.applyDisplay();
                client.addEventListener('connect', () => {
                    if (attempt !== generation) return;
                    this.clearTimers();
                    this.loading = false;
                    this.connected = true;
                    this.error = '';
                    this.stage = 'متصل';
                    this.retryCount = 0;
                });
                client.addEventListener('disconnect', () => {
                    if (attempt !== generation) return;
                    this.fail('ارتباط با نمایشگر قطع شد. وضعیت ماشین مجازی تغییر نکرده است.', 'transport', true);
                });
                client.addEventListener('securityfailure', () => {
                    if (attempt === generation) this.fail('اعتبار اتصال کنسول پذیرفته نشد. دوباره اتصال را امتحان کنید.', 'authentication', false);
                });
                client.addEventListener('credentialsrequired', () => {
                    if (attempt === generation) this.fail('اعتبار اتصال کنسول منقضی شده است. دوباره متصل شوید.', 'authentication', false);
                });
                client.addEventListener('clipboard', (event) => {
                    if (attempt === generation) this.receivedClipboard = event.detail.text;
                });
            } catch (error) {
                if (attempt !== generation || error.name === 'AbortError') return;
                this.fail('ارتباط برقرار نشد. اتصال اینترنت و وضعیت سرور را بررسی کنید.', 'network', true);
            }
        },
        fail(message, code, retryable) {
            this.clearTimers();
            this.closeTransport();
            this.loading = false;
            this.error = message;
            this.errorCode = code;
            this.retryable = retryable;
            this.stage = 'قطع اتصال';
            if (!retryable || this.retryCount >= 3) return;
            this.retryIn = 3 * (2 ** this.retryCount++);
            retryTimer = setInterval(() => {
                if (--this.retryIn <= 0) { this.clearTimers(); this.connect(); }
            }, 1000);
        },
        reconnect() { this.retryCount = 0; this.connect(); },
        cancelRetry() { this.clearTimers(); this.notice = 'تلاش خودکار متوقف شد.'; },
        disconnect() {
            this.clearTimers(); this.closeTransport(); this.loading = false; this.error = '';
            this.stage = 'قطع اتصال'; this.notice = 'اتصال کنسول قطع شد؛ ماشین مجازی همچنان کار می‌کند.';
            this.clipboard = ''; this.receivedClipboard = ''; this.mobileText = '';
        },
        applyDisplay() {
            if (!rfb) return;
            rfb.resizeSession = this.display === 'fit';
            rfb.scaleViewport = this.display === 'fit';
            rfb.clipViewport = this.drag && this.display !== 'fit';
            rfb.dragViewport = this.drag && this.display !== 'fit';
        },
        setViewOnly() { this.releaseKeys(); if (rfb) rfb.viewOnly = this.viewOnly; },
        focusScreen() { if (this.connected && !this.viewOnly) { rfb?.focus(); this.focused = true; } },
        sendKey(name) {
            if (!this.connected || this.viewOnly) return;
            rfb?.sendKey(keys[name], name);
            this.focusScreen();
        },
        sendFunction(number) { if (this.connected && !this.viewOnly) { rfb?.sendKey(0xffbd + number, `F${number}`); this.focusScreen(); } },
        toggleModifier(name) {
            if (!this.connected || this.viewOnly) return;
            this.held[name] = !this.held[name];
            rfb?.sendKey(...modifiers[name], this.held[name]);
        },
        releaseKeys() {
            for (const name of Object.keys(modifiers)) {
                if (this.held[name]) rfb?.sendKey(...modifiers[name], false);
                this.held[name] = false;
            }
        },
        ctrlAltDel() { if (this.connected && !this.viewOnly) rfb?.sendCtrlAltDel(); },
        mobileKey(name) { if (this.connected && !this.viewOnly) rfb?.sendKey(keys[name], name); },
        sendMobile(event) {
            if (event.isComposing || !this.connected || this.viewOnly) return;
            for (const character of event.target.value) {
                const point = character.codePointAt(0);
                rfb?.sendKey(point > 0xff ? 0x01000000 + point : point);
            }
            event.target.value = ''; this.mobileText = '';
        },
        mobileKeyboard() { this.panel = 'keyboard'; this.$nextTick(() => this.$refs.mobileInput.focus()); },
        sendClipboard() {
            if (!this.connected || this.viewOnly || !this.clipboard) return;
            rfb?.clipboardPasteFrom(this.clipboard);
            this.clipboard = '';
            this.notice = 'متن ارسال شد. انتقال به کلیپ‌بورد به پشتیبانی سیستم‌عامل مهمان نیاز دارد؛ سپس در ماشین Paste کنید.';
        },
        async copyReceived() {
            try { await navigator.clipboard.writeText(this.receivedClipboard); this.notice = 'متن دریافتی کپی شد.'; }
            catch { this.notice = 'اجازه کپی صادر نشد. متن را انتخاب و کپی کنید.'; }
        },
        async toggleFullscreen() {
            try {
                if (document.fullscreenElement) await document.exitFullscreen();
                else await this.$refs.workspace.requestFullscreen();
            } catch { this.notice = 'مرورگر از تمام‌صفحه پشتیبانی نمی‌کند؛ از حالت تمرکز استفاده کنید.'; }
        },
        toggleExpanded() {
            this.expanded = !this.expanded;
            if (this.expanded) { savedOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; }
            else document.body.style.overflow = savedOverflow;
        },
        diagnostics() {
            return `Console: ${config.name}\nStage: ${this.stage}\nCode: ${this.errorCode || 'none'}\nReference: ${this.reference || 'none'}\nRetries: ${this.retryCount}\nTime: ${new Date().toISOString()}`;
        },
        async copyDiagnostics() {
            try { await navigator.clipboard.writeText(this.diagnostics()); this.notice = 'گزارش اتصال برای پشتیبانی کپی شد.'; }
            catch { this.notice = 'کپی ممکن نشد؛ گزارش را از بخش راهنما انتخاب کنید.'; }
        },
        supportUrl() {
            const url = new URL(config.supportUrl || '/', window.location.href);
            url.searchParams.set('virtual_machine_id', config.machineId);
            if (this.reference) url.searchParams.set('console_reference', this.reference);
            return url.href;
        },
        confirmPower(action) { this.pendingPower = action; this.$refs.powerDialog.showModal(); },
        destroy() {
            this.clearTimers(); this.closeTransport();
            document.removeEventListener('focusin', focusListener);
            document.removeEventListener('fullscreenchange', fullscreenListener);
            document.removeEventListener('keydown', escapeListener, true);
            if (this.expanded) document.body.style.overflow = savedOverflow;
        },
    };
}
