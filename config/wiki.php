<?php

return [
    // Canonical markdown source (used by ./sync-content.sh on the host).
    'source_path' => env('WIKI_SOURCE_PATH', '../Neural-OS-Research'),

    // Local snapshot the importer parses (populated by ./sync-content.sh).
    'content_path' => base_path('content/wiki'),

    // Directories under content/wiki that are NOT concept pages (generated/derived).
    'exclude_dirs' => ['_meta'],

    // Top-level slugs that are registries, not content (excluded from the link
    // graph as sources, matching the wiki's own META_PAGES set).
    'meta_slugs' => ['index', 'log', 'glossary'],

    // The 10 fixed Wheel-of-Life domains. The NUMBER is permanent (it locks a
    // mnemonic peg image) — never renumber. Source: Mind Palace - Personal Layout.
    'domains' => [
        1 => 'Career / Mission',
        2 => 'Wealth / Money',
        3 => 'Physical Health',
        4 => 'Mental Well-being',
        5 => 'Romance / Partner',
        6 => 'Family & Friends',
        7 => 'Fun & Leisure',
        8 => 'Spirituality',
        9 => 'Environment',
        10 => 'Personal Growth / Learning',
    ],

    // The 6 changeability classes (most → least permanent).
    'palaces' => [
        'core-memory',
        'strategic-memory',
        'tactical-memory',
        'reflective-memory',
        'meta-knowledge',
        'buffer',
    ],

    // The 10 maturity levels (number => name).
    'levels' => [
        1 => 'Heard',
        2 => 'Tagged',
        3 => 'Defined',
        4 => 'Functional',
        5 => 'Linked',
        6 => 'Working',
        7 => 'Structural',
        8 => 'Bridge',
        9 => 'Foundation',
        10 => 'Universal',
    ],
];
