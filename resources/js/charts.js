/**
 * Chart.js helpers. Charts are declared in Blade with
 * <canvas data-chart='{"type":"line","labels":[...],"datasets":[...]}'></canvas>
 */
const palette = ['#4F46E5', '#0EA5E9', '#16A34A', '#F59E0B', '#DC2626', '#8B5CF6', '#14B8A6', '#EC4899', '#64748B', '#84CC16'];

function themeColors() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    return { grid: dark ? 'rgba(148,163,184,.12)' : 'rgba(17,24,39,.06)', text: dark ? '#94A3B8' : '#6B7280' };
}

function formatTzs(v) {
    const n = Number(v);
    if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(1) + 'M';
    if (Math.abs(n) >= 1e3) return (n / 1e3).toFixed(0) + 'K';
    return n.toLocaleString();
}

function buildChart(canvas) {
    if (canvas._chart) canvas._chart.destroy();
    const cfg = JSON.parse(canvas.dataset.chart);
    const { grid, text } = themeColors();
    const isRound = ['doughnut', 'pie'].includes(cfg.type);
    const money = cfg.money !== false;

    const datasets = cfg.datasets.map((ds, i) => {
        const color = ds.color || palette[i % palette.length];
        const base = { borderWidth: 2, ...ds };
        if (isRound) return { backgroundColor: palette, borderWidth: 0, hoverOffset: 6, ...ds };
        if (cfg.type === 'line') {
            return { borderColor: color, backgroundColor: color + '1A', fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 4, ...base };
        }
        return { backgroundColor: color, borderRadius: 6, maxBarThickness: 36, borderWidth: 0, ...ds };
    });

    canvas._chart = new window.Chart(canvas, {
        type: cfg.type,
        data: { labels: cfg.labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: cfg.horizontal ? 'y' : 'x',
            cutout: cfg.type === 'doughnut' ? '68%' : undefined,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: isRound || datasets.length > 1, position: isRound ? 'bottom' : 'top', labels: { color: text, usePointStyle: true, boxWidth: 8, padding: 16 } },
                tooltip: {
                    backgroundColor: '#111827', padding: 10, cornerRadius: 8,
                    callbacks: money ? { label: (c) => `${c.dataset.label || c.label}: TSh ${Number(c.raw).toLocaleString()}` } : {},
                },
            },
            scales: isRound ? {} : {
                x: { grid: { display: cfg.horizontal, color: grid }, ticks: { color: text, maxRotation: 0, autoSkip: true, ...(cfg.horizontal && money ? { callback: (v) => formatTzs(v) } : {}) }, border: { display: false } },
                y: { grid: { color: grid, display: !cfg.horizontal }, ticks: { color: text, callback: cfg.horizontal ? function (v) { const l = this.getLabelForValue(v); return l.length > 22 ? l.slice(0, 21) + '…' : l; } : (v) => (money ? formatTzs(v) : v) }, border: { display: false }, beginAtZero: true },
            },
        },
    });
}

function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach(buildChart);
}

document.addEventListener('DOMContentLoaded', () => initCharts());
document.addEventListener('dp:theme', () => initCharts());
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', ({ el }) => {
        if (el instanceof HTMLCanvasElement && el.dataset.chart) buildChart(el);
    });
    window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(() => initCharts())));
});
window.dpInitCharts = initCharts;
