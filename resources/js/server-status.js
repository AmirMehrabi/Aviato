export function customerServerStatus(config) {
    return {
        url: config.url,
        servers: Object.fromEntries((config.servers || []).map((server) => [server.id, server])),
        interval: null,
        refreshing: false,
        init() {
            if (!Object.keys(this.servers).length) return;
            this.refresh();
            this.interval = window.setInterval(() => this.refresh(), 5000);
        },
        server(id) {
            return this.servers[id] || {
                status_label: '-',
                status_class: 'bg-slate-100 text-slate-600',
                provisioning_label: '-',
                provisioning_class: 'bg-slate-100 text-slate-600',
                provisioning_pending: false,
                action_pending: false,
                is_deleting: false,
                delete_stale: false,
                is_deleted: false,
                ip: 'بدون IP',
                ssh_ready: false,
                ssh_label: 'اتصال در انتظار',
                ssh_class: 'bg-amber-50 text-amber-700',
            };
        },
        destroy() {
            window.clearInterval(this.interval);
        },
        refresh() {
            if (document.hidden || this.refreshing) return;
            const ids = Object.keys(this.servers);
            if (!ids.length) return;

            const params = new URLSearchParams();
            ids.forEach((id) => params.append('ids[]', id));

            this.refreshing = true;
            return fetch(`${this.url}?${params.toString()}`, {
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(response))
                .then((data) => {
                    (data.servers || []).forEach((server) => {
                        this.servers[server.id] = server;
                    });
                })
                .catch(() => {})
                .finally(() => { this.refreshing = false; });
        },
    };
}

export function customerServerDetailStatus(config) {
    return {
        interval: null,
        refreshing: false,
        init() {
            this.refresh();
            this.interval = window.setInterval(() => this.refresh(), 5000);
        },
        destroy() {
            window.clearInterval(this.interval);
        },
        async refresh() {
            if (document.hidden || this.refreshing) return;
            this.refreshing = true;
            try {
                const params = new URLSearchParams();
                params.append('ids[]', config.id);
                const response = await fetch(`${config.url}?${params}`, {
                    cache: 'no-store',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) return;
                const data = await response.json();
                const next = data.servers?.find((server) => server.id === config.id);
                if (next && (next.status !== config.status
                    || next.operation_id !== config.operation
                    || next.password_reset_status !== config.passwordResetStatus
                    || next.provisioning_pending !== config.provisioningPending
                    || (config.pending && !next.action_pending))) {
                    window.location.reload();
                }
            } catch (error) {
                // Keep checking after a temporary network or authentication failure.
            } finally {
                this.refreshing = false;
            }
        },
    };
}
