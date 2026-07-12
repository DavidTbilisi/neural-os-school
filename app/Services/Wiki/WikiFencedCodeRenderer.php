<?php

namespace App\Services\Wiki;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders fenced code blocks, upgrading three info-strings into live diagrams:
 *
 *   ```chart   (or ```echarts)  → an ECharts container; the fence body is the
 *                                 ECharts option as JSON. Optional `height=NNN`.
 *   ```mermaid                  → a Mermaid diagram source block.
 *   ```p5      (or ```sketch)   → a p5.js sketch; the fence body is instance-mode
 *                                 JS with `p` (the p5 instance) and `el` (the host
 *                                 element) in scope. Optional `height=NNN`.
 *
 * Every other language falls back to CommonMark's default fenced-code render,
 * so ordinary ``` code blocks (and any remaining ASCII blocks) are unchanged.
 *
 * Client side: resources/js/echarts.js auto-mounts `[data-echart]`,
 * resources/js/wiki-diagrams.js renders `.mermaid` (lazy-loading mermaid), and
 * resources/js/p5-sketch.js runs `[data-p5]` sketches (lazy-loading p5).
 *
 * SECURITY: a ```p5 block executes its JS in the reader's browser. Wiki content
 * is first-party authored (imported from the canonical research repo), so this
 * is the same trust level as an author writing a Mermaid/ECharts block. Never
 * enable this path for user-submitted content.
 */
final class WikiFencedCodeRenderer implements NodeRendererInterface
{
    private FencedCodeRenderer $default;

    public function __construct()
    {
        $this->default = new FencedCodeRenderer();
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
    {
        FencedCode::assertInstanceOf($node);
        /** @var FencedCode $node */
        $words = $node->getInfoWords();
        $lang = strtolower($words[0] ?? '');
        $body = $node->getLiteral();

        if ($lang === 'chart' || $lang === 'echarts') {
            return $this->chart($body, $words);
        }

        if ($lang === 'mermaid') {
            // HtmlElement does not escape contents, so pre-escape the source.
            return new HtmlElement(
                'pre',
                ['class' => 'mermaid not-prose'],
                htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        if ($lang === 'p5' || $lang === 'sketch') {
            return $this->sketch($body, $words);
        }

        return $this->default->render($node, $childRenderer);
    }

    /** Build a p5.js sketch figure. The body is carried in a text/plain script so
     *  markup in the source can't execute until p5-sketch.js hands it to p5. */
    private function sketch(string $body, array $words): HtmlElement
    {
        $height = '360px';
        foreach ($words as $w) {
            if (preg_match('/^height=(\d+)(px|rem|vh)?$/', $w, $m)) {
                $height = $m[1].(($m[2] ?? '') ?: 'px');
            }
        }

        // </script> in author code would close the carrier early — neutralise it.
        $safe = str_replace('</script', '<\/script', $body);
        $source = new HtmlElement('script', ['type' => 'text/plain', 'data-p5-src' => ''], $safe, false);
        $host = new HtmlElement('div', [
            'class' => 'wiki-sketch not-prose',
            'data-p5' => '',
            'style' => 'min-height: '.$height,
        ], $source);

        return new HtmlElement('figure', ['class' => 'wiki-figure'], $host);
    }

    /** Build an ECharts figure — or a visible error note if the JSON is invalid. */
    private function chart(string $body, array $words): HtmlElement
    {
        $decoded = json_decode(trim($body), true);
        if (! is_array($decoded)) {
            return new HtmlElement(
                'div',
                ['class' => 'wiki-figure wiki-chart-error'],
                'Invalid chart JSON: '.htmlspecialchars(json_last_error_msg(), ENT_QUOTES),
            );
        }

        $height = '360px';
        foreach ($words as $w) {
            if (preg_match('/^height=(\d+)(px|rem|vh)?$/', $w, $m)) {
                $height = $m[1].(($m[2] ?? '') ?: 'px');
            }
        }

        // JSON_HEX_TAG escapes < and > (→ <>) so the option is safe to
        // embed inside a <script> element; JSON.parse restores it client-side.
        $json = json_encode(
            $decoded,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE,
        );

        $script = new HtmlElement('script', ['type' => 'application/json', 'data-echart-option' => ''], $json);
        $chart = new HtmlElement('div', [
            'class' => 'wiki-chart not-prose',
            'data-echart' => '',
            'style' => 'height: '.$height,
        ], $script);

        return new HtmlElement('figure', ['class' => 'wiki-figure'], $chart);
    }
}
