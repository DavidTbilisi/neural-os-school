/**
 * Apache ECharts integration for the learner front-end.
 *
 * Loaded from resources/js/app.js, so every page served through a layout that
 * pulls in `@vite(['resources/js/app.js'])` gets charting for free.
 *
 * Two ways to use it:
 *
 *   1. Declarative (preferred) — drop an `<x-echart>` Blade component, or any
 *      element carrying `data-echart` with the option supplied as a nested
 *      `<script type="application/json" data-echart-option>`. This module finds
 *      and mounts it automatically on load and after Livewire navigation.
 *
 *   2. Imperative — call `window.mountEChart(el, option)` yourself, e.g. from a
 *      Livewire event listener that pushes fresh data.
 *
 * `window.echarts` is exposed for ad-hoc console use and advanced cases.
 *
 * Bundle note: this pulls the full ECharts build (~330 KB gzip). If size ever
 * matters, swap to the tree-shakeable `echarts/core` API and register only the
 * charts/components actually used.
 */
import * as echarts from 'echarts';

window.echarts = echarts;

// Design-token-aware default themes so charts are legible in both modes without
// every author setting axis/text colors. Author options (series colors, etc.)
// layer on top. Colors mirror tokens.css (warm ink / candlelit ink).
function nosTheme({ text, axis, split, line }) {
    return {
        textStyle: { color: text },
        title: { textStyle: { color: text } },
        legend: { textStyle: { color: text } },
        categoryAxis: {
            axisLine: { lineStyle: { color: line } },
            axisTick: { lineStyle: { color: line } },
            axisLabel: { color: axis },
            splitLine: { lineStyle: { color: split } },
        },
        valueAxis: {
            axisLine: { lineStyle: { color: line } },
            axisTick: { lineStyle: { color: line } },
            axisLabel: { color: axis },
            splitLine: { lineStyle: { color: split } },
        },
        timeAxis: { axisLabel: { color: axis }, axisLine: { lineStyle: { color: line } } },
    };
}
echarts.registerTheme('nos-light', nosTheme({ text: '#2B2620', axis: '#63584A', split: '#EDE6D8', line: '#CFC3AB' }));
echarts.registerTheme('nos-dark', nosTheme({ text: '#ECE4D3', axis: '#A99B84', split: '#29241D', line: '#4E463A' }));

const isDark = () => document.documentElement.classList.contains('dark');

// Remember each chart's option keyed by element. ECharts clears the container on
// dispose() (removing the inline <script> the option came from), so re-theming
// on a light/dark toggle must replay the cached option, not re-read the DOM.
const chartOptions = new WeakMap();

// One ResizeObserver drives every chart — cheaper than one per instance.
const resizeObserver = new ResizeObserver((entries) => {
    for (const entry of entries) {
        const chart = echarts.getInstanceByDom(entry.target);
        if (chart) chart.resize();
    }
});

/**
 * Mount an ECharts option onto a DOM element (idempotent — re-mounting the same
 * element disposes the previous instance first, so it is safe to call after a
 * Livewire DOM update).
 *
 * @param {HTMLElement} el     target element (must have a resolved height)
 * @param {object}      option ECharts option object
 * @param {object}      [opts] { theme?: string } passed to echarts.init
 * @returns {import('echarts').ECharts|undefined}
 */
function mountEChart(el, option, opts = {}) {
    if (!el || !option) return undefined;

    const existing = echarts.getInstanceByDom(el);
    if (existing) existing.dispose();

    // Explicit theme wins; otherwise pick the token theme matching the mode.
    const theme = opts.theme ?? el.dataset.echartTheme ?? (isDark() ? 'nos-dark' : 'nos-light');
    const chart = echarts.init(el, theme, { renderer: 'canvas' });
    chart.setOption(option);
    chartOptions.set(el, option);
    resizeObserver.observe(el);
    return chart;
}

window.mountEChart = mountEChart;

// Let late-initializing consumers (e.g. Filament/Alpine widgets that render
// after this module) know the helper is ready. Anyone who missed it can still
// check `window.mountEChart` directly — assignment above is synchronous.
window.dispatchEvent(new Event('echarts:ready'));

/**
 * Read the JSON option carried by a declarative `[data-echart]` element.
 * Prefers a nested `<script type="application/json" data-echart-option>` (robust
 * for large configs); falls back to a `data-echart-option` attribute.
 */
function readOption(el) {
    const script = el.querySelector('script[data-echart-option]');
    const raw = script ? script.textContent : el.dataset.echartOption;
    if (!raw) return null;
    try {
        return JSON.parse(raw);
    } catch (e) {
        console.error('[echart] invalid option JSON on', el, e);
        return null;
    }
}

/** Find every declarative chart under `root` and mount the ones not yet mounted. */
function mountAll(root = document) {
    root.querySelectorAll('[data-echart]').forEach((el) => {
        if (echarts.getInstanceByDom(el)) return; // already mounted
        const option = readOption(el);
        if (option) mountEChart(el, option);
    });
}

// Initial render.
document.addEventListener('DOMContentLoaded', () => mountAll());

// Livewire `wire:navigate` swaps the page without a full reload. Dispose stale
// instances before the swap, then re-scan the new DOM afterwards.
document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('[data-echart]').forEach((el) => {
        const chart = echarts.getInstanceByDom(el);
        if (chart) chart.dispose();
    });
});
document.addEventListener('livewire:navigated', () => mountAll());

// Re-theme charts when the user toggles light/dark (the toggle dispatches this).
// Charts read their option from the still-present JSON <script>, so disposing
// and re-mounting picks up the new mode's default theme.
window.addEventListener('theme-changed', () => {
    document.querySelectorAll('[data-echart]').forEach((el) => {
        // Prefer the cached option (dispose() will wipe the DOM <script>).
        const option = chartOptions.get(el) ?? readOption(el);
        const chart = echarts.getInstanceByDom(el);
        if (chart) chart.dispose();
        if (option) mountEChart(el, option);
    });
});
