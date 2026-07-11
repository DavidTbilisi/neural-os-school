<?php

namespace App\Services\Wiki;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Renderer\Block\FencedCodeRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders fenced code blocks, upgrading two info-strings into live diagrams:
 *
 *   ```chart   (or ```echarts)  → an ECharts container; the fence body is the
 *                                 ECharts option as JSON. Optional `height=NNN`.
 *   ```mermaid                  → a Mermaid diagram source block.
 *
 * Every other language falls back to CommonMark's default fenced-code render,
 * so ordinary ``` code blocks (and the ASCII "Visual" blocks) are unchanged.
 *
 * Client side: resources/js/echarts.js auto-mounts `[data-echart]`, and
 * resources/js/wiki-diagrams.js renders `.mermaid` (lazy-loading mermaid).
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

        return $this->default->render($node, $childRenderer);
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
                $height = $m[1].($m[2] ?: 'px');
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
