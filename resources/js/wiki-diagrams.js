/**
 * Render Mermaid diagrams authored as ```mermaid fenced blocks in wiki pages
 * (see App\Services\Wiki\WikiFencedCodeRenderer, which emits <pre class="mermaid">).
 *
 * Mermaid is a large dependency, so it is dynamically imported only when a page
 * actually contains a diagram — Vite emits it as a separate chunk fetched on
 * demand, keeping it off every other page. ECharts `chart` blocks are handled
 * separately by resources/js/echarts.js (which auto-mounts `[data-echart]`).
 */
let mermaidPromise = null;

function loadMermaid() {
    return (mermaidPromise ??= import('mermaid').then((m) => m.default));
}

async function renderMermaid() {
    const nodes = document.querySelectorAll('pre.mermaid:not([data-processed])');
    if (!nodes.length) return;

    const mermaid = await loadMermaid();
    const dark = document.documentElement.classList.contains('dark');

    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        // 'neutral' is a quiet grayscale that suits the paper aesthetic; 'dark'
        // for the warm night theme. (Diagrams don't re-theme on toggle — they're
        // rendered once; a fresh navigation picks up the current theme.)
        theme: dark ? 'dark' : 'neutral',
        fontFamily: 'inherit',
    });

    try {
        await mermaid.run({ nodes: [...nodes] });
    } catch (e) {
        console.error('[mermaid] render failed', e);
    }
}

document.addEventListener('DOMContentLoaded', renderMermaid);
document.addEventListener('livewire:navigated', renderMermaid);

/**
 * Render a mermaid diagram from source into a host element, on demand.
 *
 * Mermaid cannot lay out a diagram inside a `display:none` container (it measures
 * text and collapses to an empty 16×16 SVG). Tabbed UIs must therefore defer
 * rendering until the panel is actually visible — the representations panel
 * (resources/views/livewire/partials/representations.blade.php) calls this the
 * first time its "Structure flow" tab is revealed.
 */
window.renderMermaidInto = async (host, src) => {
    if (!host || host.dataset.rendered) return;
    host.dataset.rendered = '1';
    const pre = document.createElement('pre');
    pre.className = 'mermaid not-prose';
    pre.textContent = src;
    host.appendChild(pre);

    const mermaid = await loadMermaid();
    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'neutral',
        fontFamily: 'inherit',
    });
    try {
        await mermaid.run({ nodes: [pre] });
    } catch (e) {
        console.error('[mermaid] on-demand render failed', e);
        host.dataset.rendered = '';
    }
};
