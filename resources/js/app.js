import Alpine from 'alpinejs';
import RFB from '@novnc/novnc';
import { Editor } from '@toast-ui/editor';
import '@toast-ui/editor/dist/toastui-editor.css';
import { Chart, registerables } from 'chart.js';
import PersianDate from 'persian-date';

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

window.walletTransactions = function walletTransactions(config) {
    return {
        type: config.type || 'all',
        search: config.search || '',
        from: config.from || '',
        to: config.to || '',
        page: 1,
        loading: false,
        html: config.html || '',
        hasPages: config.hasPages || false,

        pickerTarget: null,
        pickerYear: 0,
        pickerMonth: 0,

        async load() {
            this.loading = true;

            try {
                const params = new URLSearchParams();

                if (this.type !== 'all') params.set('type', this.type);
                if (this.search) params.set('search', this.search);
                if (this.from) params.set('from', this.from);
                if (this.to) params.set('to', this.to);
                if (this.page > 1) params.set('page', this.page);

                const response = await fetch(`/wallet/transactions?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                const data = await response.json();

                if (! response.ok) {
                    throw new Error(data.message || 'Failed to load transactions');
                }

                const url = new URL(window.location);

                if (this.type !== 'all') url.searchParams.set('type', this.type);
                else url.searchParams.delete('type');

                if (this.search) url.searchParams.set('search', this.search);
                else url.searchParams.delete('search');

                if (this.from) url.searchParams.set('from', this.from);
                else url.searchParams.delete('from');

                if (this.to) url.searchParams.set('to', this.to);
                else url.searchParams.delete('to');

                url.searchParams.delete('page');
                window.history.replaceState({}, '', url.toString());

                this.html = data.html;
                this.hasPages = data.hasPages;
            } catch (e) {
                // keep existing content on error
            } finally {
                this.loading = false;
            }
        },

        setType(type) {
            this.type = type;
            this.page = 1;
            this.load();
        },

        handlePageClick(e) {
            const link = e.target.closest('a[href]');

            if (! link) return;

            e.preventDefault();
            const url = new URL(link.href);
            this.page = url.searchParams.get('page') || 1;
            this.load();
        },

        clearFilters() {
            this.type = 'all';
            this.search = '';
            this.from = '';
            this.to = '';
            this.page = 1;
            this.load();
        },

        openPicker(target) {
            if (this.pickerTarget === target) {
                this.pickerTarget = null;
                return;
            }

            this.pickerTarget = target;

            const value = this[target];

            if (value) {
                const parts = value.split('/');

                if (parts.length === 3) {
                    this.pickerYear = parseInt(parts[0]);
                    this.pickerMonth = parseInt(parts[1]);
                    return;
                }
            }

            const now = new PersianDate();
            this.pickerYear = now.year();
            this.pickerMonth = now.month();
        },

        closePicker() {
            this.pickerTarget = null;
        },

        selectDate(day) {
            const value = `${this.pickerYear}/${String(this.pickerMonth).padStart(2, '0')}/${String(day).padStart(2, '0')}`;

            this[this.pickerTarget] = value;
            this.pickerTarget = null;
            this.page = 1;
            this.load();
        },

        prevCalendarMonth() {
            let y = this.pickerYear;
            let m = this.pickerMonth - 1;

            if (m < 1) { m = 12; y--; }
            this.pickerYear = y;
            this.pickerMonth = m;
        },

        nextCalendarMonth() {
            let y = this.pickerYear;
            let m = this.pickerMonth + 1;

            if (m > 12) { m = 1; y++; }
            this.pickerYear = y;
            this.pickerMonth = m;
        },

        get calendarWeeks() {
            const start = new PersianDate([this.pickerYear, this.pickerMonth, 1]);
            const daysInMonth = start.daysInMonth();
            const startWeekday = start.day();
            const weeks = [];
            let week = new Array(7).fill(null);
            let dayIndex = startWeekday - 1;

            for (let d = 1; d <= daysInMonth; d++) {
                week[dayIndex] = d;
                dayIndex++;

                if (dayIndex === 7) {
                    weeks.push(week);
                    week = new Array(7).fill(null);
                    dayIndex = 0;
                }
            }

            if (dayIndex > 0) weeks.push(week);

            return weeks;
        },

        get calendarMonthName() {
            return new PersianDate([this.pickerYear, this.pickerMonth, 1]).format('MMMM');
        },
    };
};

Alpine.start();
