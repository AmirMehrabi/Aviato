import Alpine from 'alpinejs';
import RFB from '@novnc/novnc';
import { Editor } from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';

window.Alpine = Alpine;

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-ticket-editor]').forEach((textarea) => {
        if (textarea.dataset.editorReady) return;

        const container = document.createElement('div');
        container.className = 'ticket-editor rounded-xl border border-slate-200 bg-white';
        textarea.classList.add('hidden');
        textarea.after(container);

        const editor = new Editor({
            el: container,
            height: '360px',
            initialEditType: 'wysiwyg',
            previewStyle: 'tab',
            initialValue: textarea.value || '',
            usageStatistics: false,
            toolbarItems: [
                ['heading', 'bold', 'italic', 'strike'],
                ['hr', 'quote'],
                ['ul', 'ol', 'task'],
                ['link', 'code', 'codeblock'],
            ],
        });

        textarea.form?.addEventListener('submit', () => {
            textarea.value = editor.getMarkdown();
        });

        textarea.dataset.editorReady = '1';
    });
});

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

            const wsUrl = this.websocketUrl(session.websocket_url);
            this.rfb = new RFB(this.$refs.screen, wsUrl, {
                shared: true,
                credentials: {
                    password: session.password || '',
                },
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
        websocketUrl(url) {
            if (url.startsWith('ws://') || url.startsWith('wss://')) {
                return url;
            }

            return `${window.location.protocol === 'https:' ? 'wss' : 'ws'}://${window.location.host}${url.startsWith('/') ? url : `/${url}`}`;
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
