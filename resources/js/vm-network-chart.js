const millisecondsPerDay = 86_400_000;
const jalaliDate = new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone: 'UTC',
});

export function vmNetworkChart(series, startDate = series[0].date, endDate = series.at(-1).date) {
    let chart;

    return {
        init() {
            this.$nextTick(() => {
                const days = new Map(series.map(day => [Date.parse(day.date), day]));
                const firstDay = Date.parse(startDate);
                const lastDay = Date.parse(endDate);
                const dates = Array.from(
                    { length: (lastDay - firstDay) / millisecondsPerDay + 1 },
                    (_, index) => firstDay + index * millisecondsPerDay,
                );

                const dataset = (key, label, borderColor) => ({
                    label,
                    data: dates.map(date => days.has(date) ? days.get(date)[key] / 1e9 : null),
                    borderColor,
                    tension: 0.2,
                });

                chart = new window.Chart(this.$refs.canvas, {
                    type: 'line',
                    data: {
                        labels: dates.map(date => jalaliDate.format(date)),
                        datasets: [
                            dataset('ingress', 'ورودی (GB)', '#0069ff'),
                            dataset('egress', 'خروجی (GB)', '#047857'),
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 0,
                                    maxTicksLimit: 9,
                                    callback(index) { return this.getLabelForValue(index).slice(-5); },
                                },
                            },
                            y: { beginAtZero: true, title: { display: true, text: 'GB' } },
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
