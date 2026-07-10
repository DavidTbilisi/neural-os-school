<?php

namespace App\Support;

class Slug
{
    /**
     * Normalize a wiki-link target or filename to a slug, matching the Python
     * slug_of() in tools/wiki_link_graph.py so our link graph agrees with the
     * wiki's own: lowercase → last path segment → drop .md → spaces to hyphens →
     * collapse hyphens → trim hyphens. (No other punctuation is stripped.)
     */
    public static function make(string $s): string
    {
        $s = strtolower(trim($s));

        if (str_contains($s, '/')) {
            $s = substr($s, strrpos($s, '/') + 1);
        }

        $s = preg_replace('/\.md$/', '', $s);
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);

        return trim($s, '-');
    }
}
