// يرسم بيانات المتجر الممررة من Blade فقط، ولا يعيد حساب المبيعات أو الأرباح.
const root = document.querySelector('[data-store-sales-chart-config]');

if (root) {
    const config = JSON.parse(root.dataset.storeSalesChartConfig || '{}');
    const chartData = config.chartData || [];
    const chartLabels = config.chartLabels || [];

    function loadChartJs() {
        return new Promise((resolve, reject) => {
            if (typeof window.Chart !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    async function createChart() {
        const container = document.getElementById('salesChartContainer');
        if (!container) return;

        try {
            await loadChartJs();
            const canvas = document.createElement('canvas');
            container.innerHTML = '';
            container.appendChild(canvas);

            new window.Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'المبيعات',
                        data: chartData,
                        borderColor: '',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { callback: (value) => `${value} ر.س` } } },
                },
            });
        } catch (error) {
            console.error('Chart error:', error);
        }
    }

    if (chartData.length > 0 && !chartData.every((value) => value === 0)) {
        window.setTimeout(createChart, 500);
    }
}
