const units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB'];
const number = new Intl.NumberFormat('en', { maximumFractionDigits: 2 });
const millisecondsPerDay = 86_400_000;

function unitIndex(bytes) {
    return Math.min(units.length - 1, Math.floor(Math.log10(Math.max(1, bytes)) / 3));
}

function formatBytes(bytes, unit = unitIndex(bytes)) {
    return `\u2066${number.format(bytes / 1000 ** unit)} ${units[unit]}\u2069`;
}

export function customerNetworkChart(series) {
    let chart;

    return {
        init() {
            this.$nextTick(() => {
                const days = new Map(series.map(day => [Date.parse(day.date), day]));
                const firstDay = Date.parse(series[0].date);
                const lastDay = Date.parse(series.at(-1).date);
                const dates = Array.from(
                    { length: (lastDay - firstDay) / millisecondsPerDay + 1 },
                    (_, index) => firstDay + index * millisecondsPerDay,
                );
                const peak = Math.max(...series.map(day => Math.max(day.ingress, day.egress)));
                const unit = unitIndex(peak);
                const font = { family: getComputedStyle(this.$refs.canvas).fontFamily, size: 11 };

                const dataset = (key, label, color) => ({
                    label,
                    data: dates.map(date => days.get(date)?.[key] ?? null),
                    backgroundColor: color,
                    borderRadius: 3,
                    maxBarThickness: 20,
                });

                chart = new window.Chart(this.$refs.canvas, {
                    type: 'bar',
                    data: {
                        labels: dates.map(date => new Date(date).toISOString().slice(0, 10)),
                        datasets: [
                            dataset('ingress', 'ورودی', '#2563eb'),
                            dataset('egress', 'خروجی', '#059669'),
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                rtl: true,
                                textDirection: 'rtl',
                                titleFont: font,
                                bodyFont: font,
                                padding: 10,
                                callbacks: {
                                    label: ({ dataset, raw }) => `${dataset.label}: ${formatBytes(raw)}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { color: '#cbd5e1' },
                                ticks: {
                                    color: '#475569',
                                    font,
                                    maxRotation: 0,
                                    maxTicksLimit: 9,
                                    callback(index) { return this.getLabelForValue(index).slice(5); },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#e2e8f0' },
                                border: { display: false },
                                ticks: {
                                    color: '#475569',
                                    font,
                                    maxTicksLimit: 5,
                                    callback: value => formatBytes(value, unit),
                                },
                            },
                        },
                    },
                });
            });
        },
        destroy() {
            chart?.destroy();
        },
    };
}
