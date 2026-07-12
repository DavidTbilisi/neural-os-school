/**
 * Run p5.js sketches authored as ```p5 (or ```sketch) fenced blocks in wiki
 * pages (see App\Services\Wiki\WikiFencedCodeRenderer, which emits
 * <div data-p5><script type="text/plain" data-p5-src>…</script></div>).
 *
 * p5 is a large dependency, so it is dynamically imported only when a page
 * actually contains a sketch — Vite emits it as a separate chunk fetched on
 * demand, keeping it off every other page (same strategy as wiki-diagrams.js).
 *
 * Authoring contract: the block body is instance-mode p5 code with two names in
 * scope — `p` (the p5 instance: p.setup, p.draw, p.createCanvas, …) and `el`
 * (the host <div>, useful for sizing to p.createCanvas(el.clientWidth, h)).
 * `p.isDark` is a boolean convenience for theming to the reader's mode.
 *
 * SECURITY: the body is executed via new Function. Wiki content is first-party
 * authored, so this matches the trust level of the ECharts/Mermaid blocks. Do
 * not point this at user-submitted content.
 */
let p5Promise = null;

function loadP5() {
    return (p5Promise ??= import('p5').then((m) => m.default));
}

// Track live instances per host so a Livewire navigation can stop their draw
// loops (an orphaned p5 keeps calling requestAnimationFrame on a detached DOM).
const instances = new WeakMap();

async function mountSketch(host) {
    if (!host || host.dataset.processed) return;
    host.dataset.processed = '1';

    const carrier = host.querySelector('script[data-p5-src]');
    const src = carrier ? carrier.textContent : '';
    if (!src.trim()) return;

    let factory;
    try {
        // eslint-disable-next-line no-new-func
        factory = new Function('p', 'el', src);
    } catch (e) {
        return fail(host, e);
    }

    const p5 = await loadP5();
    try {
        const inst = new p5((p) => {
            p.isDark = document.documentElement.classList.contains('dark');
            factory(p, host);
        }, host);
        instances.set(host, inst);
    } catch (e) {
        fail(host, e);
    }
}

function fail(host, e) {
    console.error('[p5] sketch failed', e);
    host.dataset.processed = '';
    host.innerHTML =
        '<div class="wiki-chart-error">Sketch error: ' +
        String(e && e.message ? e.message : e).replace(/[<>&]/g, '') +
        '</div>';
}

function mountAll(root = document) {
    root.querySelectorAll('[data-p5]:not([data-processed])').forEach(mountSketch);
}

function disposeAll() {
    document.querySelectorAll('[data-p5]').forEach((host) => {
        const inst = instances.get(host);
        if (inst) {
            inst.remove();
            instances.delete(host);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => mountAll());
document.addEventListener('livewire:navigating', disposeAll);
document.addEventListener('livewire:navigated', () => mountAll());
