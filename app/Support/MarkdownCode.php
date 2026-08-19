<?php

namespace App\Support;

/**
 * Masks fenced code blocks and inline code spans so whole-document text
 * transforms never rewrite code samples.
 *
 * The wiki reader resolves `[[wiki-links]]` by regex over the raw markdown,
 * before CommonMark ever sees it. That is fine until a page's subject matter
 * *is* double-bracket syntax — `wiki/linux/test-and-double-bracket.md` is
 * wall-to-wall `[[ -f $f ]]` — at which point every code sample gets silently
 * stripped of its brackets and the link graph fills with slugs like `f-f`.
 *
 * Masking is deliberately conservative: only fenced blocks (``` / ~~~) and
 * inline spans (`…`) are hidden. Indented code blocks are left alone, because
 * four-space indentation is ambiguous with list continuation in this corpus.
 */
final class MarkdownCode
{
    /** NUL-delimited so it cannot collide with anything in the markdown. */
    private const PLACEHOLDER = "\x00c%d\x00";

    /**
     * Run $fn over the document with code masked out, then restore the code.
     *
     * @param  callable(string): string  $fn
     */
    public static function outsideCode(string $md, callable $fn): string
    {
        $store = [];

        return strtr($fn(self::mask($md, $store)), $store);
    }

    /** The document with fenced blocks and inline code spans removed entirely. */
    public static function withoutCode(string $md): string
    {
        $store = [];
        $masked = self::mask($md, $store);

        return str_replace(array_keys($store), '', $masked);
    }

    /** @param  array<string,string>  $store  filled with placeholder => original code */
    private static function mask(string $md, array &$store): string
    {
        $next = 0;
        $keep = function (string $code) use (&$store, &$next): string {
            $key = sprintf(self::PLACEHOLDER, $next++);
            $store[$key] = $code;

            return $key;
        };

        $out = '';
        $prose = '';
        $block = '';
        $fenceChar = null;
        $fenceLen = 0;

        foreach (preg_split('/(?<=\n)/', $md) as $line) {
            if ($fenceChar === null) {
                if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m)) {
                    $out .= self::maskInline($prose, $keep);
                    $prose = '';
                    $fenceChar = $m[1][0];
                    $fenceLen = strlen($m[1]);
                    $block = $line;

                    continue;
                }

                $prose .= $line;

                continue;
            }

            $block .= $line;
            if (preg_match('/^ {0,3}'.preg_quote($fenceChar, '/').'{'.$fenceLen.',}[ \t]*\R?$/', $line)) {
                $out .= $keep($block);
                $block = '';
                $fenceChar = null;
            }
        }

        // An unclosed fence runs to end of file — treat the remainder as code.
        if ($block !== '') {
            $out .= $keep($block);
        }

        return $out.self::maskInline($prose, $keep);
    }

    /**
     * A backtick run of length n opens a span that the next run of the same
     * length closes, and the span may not contain that run. Spans are kept to a
     * single line so an unmatched stray backtick cannot swallow the document.
     */
    private static function maskInline(string $text, callable $keep): string
    {
        return preg_replace_callback(
            '/(`+)(?:(?!\1).)*\1/',
            fn (array $m) => $keep($m[0]),
            $text,
        );
    }
}
