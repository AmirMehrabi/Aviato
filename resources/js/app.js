import Alpine from 'alpinejs';
import RFB from '@novnc/novnc';

window.Alpine = Alpine;

window.customerVmConsole = function customerVmConsole(config) {
    return {
        rfb: null,
        loading: false,
        connected: false,
        error: '',
        statusText: 'در حال اتصال...',
        connect() {
            this.error = '';
            this.loading = true;
            this.statusText = 'در حال ساخت نشست Console...';

            fetch(config.sessionUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            })
                .then((response) => response.ok ? response.json() : response.json().then((data) => Promise.reject(data)))
                .then((session) => {
                    this.statusText = 'در حال اتصال به Console...';
                    this.openRfb(session);
                })
                .catch((error) => {
                    this.loading = false;
                    this.connected = false;
                    this.error = error?.error || error?.message || 'Console در دسترس نیست.';
                    this.statusText = 'اتصال ناموفق';
                });
        },
        openRfb(session) {
            this.disconnect(false);

            const wsUrl = this.proxyWebsocketUrl(session.proxy_url, session.session_id);
            this.rfb = new RFB(this.$refs.screen, wsUrl, {
                shared: true,
            });
            this.rfb.scaleViewport = true;
            this.rfb.resizeSession = true;
            this.rfb.clipViewport = false;

            this.rfb.addEventListener('connect', () => {
                this.loading = false;
                this.connected = true;
                this.error = '';
                this.statusText = 'متصل';
            });
            this.rfb.addEventListener('disconnect', (event) => {
                this.loading = false;
                this.connected = false;
                this.statusText = event.detail?.clean ? 'قطع شد' : 'اتصال قطع شد';
            });
            this.rfb.addEventListener('securityfailure', () => {
                this.loading = false;
                this.connected = false;
                this.error = 'احراز هویت Console ناموفق بود.';
                this.statusText = 'اتصال ناموفق';
            });
        },
        proxyWebsocketUrl(proxyUrl, sessionId) {
            const base = proxyUrl.startsWith('ws://') || proxyUrl.startsWith('wss://')
                ? proxyUrl
                : `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.host}${proxyUrl.startsWith('/') ? proxyUrl : `/${proxyUrl}`}`;
            const url = new URL(base);
            url.searchParams.set('session', sessionId);

            return url.toString();
        },
        reconnect() {
            this.disconnect(false);
            this.connect();
        },
        disconnect(markClean = true) {
            if (this.rfb) {
                this.rfb.disconnect();
                this.rfb = null;
            }

            if (markClean) {
                this.connected = false;
                this.loading = false;
                this.statusText = 'قطع شد';
            }
        },
    };
};

Alpine.start();
