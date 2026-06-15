import Alpine from 'alpinejs';
import RFB from '@novnc/novnc';
import { Editor } from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;
window.Alpine = Alpine;

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-ticket-editor]').forEach((textarea) => {
        if (textarea.dataset.editorReady) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'ticket-composer mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white';
        const toolbar = document.createElement('div');
        toolbar.className = 'ticket-composer-toolbar flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-2';
        const container = document.createElement('div');
        container.className = 'ticket-editor bg-white';
        textarea.classList.add('hidden');
        textarea.after(wrapper);
        wrapper.append(toolbar, container);

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

        const commands = [
            { label: 'B', title: 'Bold', command: 'bold', className: 'font-black' },
            { label: 'I', title: 'Italic', command: 'italic', className: 'italic' },
            { label: 'H', title: 'Heading', command: 'heading' },
            { label: '•', title: 'Bullet list', command: 'bulletList' },
            { label: '1.', title: 'Ordered list', command: 'orderedList' },
            { label: '“”', title: 'Quote', command: 'blockQuote' },
            { label: '<>', title: 'Code', command: 'code' },
            { label: 'Link', title: 'Link', command: 'addLink' },
        ];

        commands.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.title = item.title;
            button.className = `grid h-9 min-w-9 place-items-center rounded-lg border border-slate-200 bg-white px-2 text-xs font-black text-slate-700 transition hover:border-[#B8D6FF] hover:bg-[#EBF3FF] hover:text-[#0069FF] ${item.className || ''}`;
            button.textContent = item.label;
            button.addEventListener('click', () => editor.exec(item.command));
            toolbar.append(button);
        });

        const attachmentInput = textarea.form?.querySelector('[data-ticket-attachments]');
        if (attachmentInput) {
            const attachButton = document.createElement('button');
            attachButton.type = 'button';
            attachButton.title = 'Attach files';
            attachButton.className = 'mr-auto inline-flex h-9 items-center gap-2 rounded-lg border border-[#B8D6FF] bg-white px-3 text-xs font-black text-[#0069FF] transition hover:bg-[#EBF3FF]';
            attachButton.textContent = 'Attach file';
            attachButton.addEventListener('click', () => attachmentInput.click());
            toolbar.append(attachButton);

            const fileSummary = document.createElement('div');
            fileSummary.className = 'ticket-attachment-summary hidden border-t border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-600';
            wrapper.append(fileSummary);

            attachmentInput.addEventListener('change', () => {
                const names = Array.from(attachmentInput.files || []).map((file) => `${file.name} (${Math.ceil(file.size / 1024)} KB)`);
                fileSummary.textContent = names.length ? `Attached: ${names.join('، ')}` : '';
                fileSummary.classList.toggle('hidden', names.length === 0);
            });
        }

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

window.adminNotificationDropdown = function adminNotificationDropdown(config) {
    return {
        items: config.items || [],
        unreadCount: Number(config.unreadCount || 0),
        csrf: config.csrf || '',
        markReadUrlTemplate: config.markReadUrlTemplate || '',
        markAllReadUrl: config.markAllReadUrl || '',
        markingAll: false,
        markingIds: {},

        isUnread(notification) {
            return ! notification.read;
        },

        markReadUrl(notificationId) {
            return this.markReadUrlTemplate.replace('__NOTIFICATION__', encodeURIComponent(notificationId));
        },

        setNotificationRead(notificationId) {
            const notification = this.items.find((item) => item.id === notificationId);
            if (! notification || notification.read) {
                return;
            }

            notification.read = true;
            if (this.unreadCount > 0) {
                this.unreadCount -= 1;
            }

            window.dispatchEvent(new CustomEvent('admin-notification-unread-changed', {
                detail: { count: this.unreadCount },
            }));
        },

        async markAllRead() {
            if (this.markingAll || this.unreadCount === 0) {
                return;
            }

            this.markingAll = true;

            try {
                const response = await fetch(this.markAllReadUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                });

                const data = await response.json();
                if (! response.ok) {
                    throw new Error(data?.message || 'Unable to mark notifications as read.');
                }

                this.items.forEach((notification) => {
                    notification.read = true;
                });
                this.unreadCount = Number(data.unread_count ?? 0);
                window.dispatchEvent(new CustomEvent('admin-notification-unread-changed', {
                    detail: { count: this.unreadCount },
                }));
            } finally {
                this.markingAll = false;
            }
        },

        async markRead(notificationId, navigateTo = null) {
            if (this.markingIds[notificationId]) {
                if (navigateTo) {
                    window.location.assign(navigateTo);
                }

                return;
            }

            this.markingIds = { ...this.markingIds, [notificationId]: true };

            try {
                const response = await fetch(this.markReadUrl(notificationId), {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({}),
                });

                const data = await response.json();
                if (! response.ok) {
                    throw new Error(data?.message || 'Unable to mark notification as read.');
                }

                this.setNotificationRead(notificationId);
                this.unreadCount = Number(data.unread_count ?? this.unreadCount);
                window.dispatchEvent(new CustomEvent('admin-notification-unread-changed', {
                    detail: { count: this.unreadCount },
                }));

                if (navigateTo) {
                    window.location.assign(navigateTo);
                }
            } catch (error) {
                if (navigateTo) {
                    window.location.assign(navigateTo);
                    return;
                }

                console.error(error);
            } finally {
                const next = { ...this.markingIds };
                delete next[notificationId];
                this.markingIds = next;
            }
        },

        openNotification(notification) {
            this.markRead(notification.id, notification.url);
        },
    };
};

Alpine.start();
