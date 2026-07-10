# Neural OS School — Architecture Decisions

Locked from the planning conversation on 2026-07-10. Anything marked **(assumed)**
is a sensible default I picked to keep momentum — tell me to change it.

## What this is

A **multi-user public web school** built from the `Neural-OS-Research` markdown wiki
(~1,254 pages, ~15k `[[link]]` edges, 932 glossary terms) plus its pedagogy layer
(METER, drill ladders, encoders, memory palaces, spaced repetition, gyms).

The wiki is a *personal* knowledge base being turned into a *public* product, so
publishing is deliberate and gated — not "import everything and expose it."

## Locked decisions (from Q&A)

| # | Decision | Choice |
|---|----------|--------|
| 1 | Audience | **Multi-user public school now** — real accounts, enrollment, per-learner progress, roles. |
| 2 | Canonical content | **Markdown stays canonical.** Laravel imports `../Neural-OS-Research/wiki/**/*.md` read-only and adds the interactive layer. The Obsidian + Claude Code + `tools/` + git authoring pipeline stays 100% intact. |
| 3 | First slice | **Knowledge-base + admin dashboard** — port the `dashboard-scores` model (Complexity / Acquirement / Absorbed-%) + coverage by domain·palace·level + graph health. Works day-one from the import; per-learner dashboards come after gyms/SRS exist. |
| 4 | ~100 existing HTML tools (gyms, /explain, dashboards) | **Rebuild natively** in Laravel/Livewire over time so everything is one stack with unified tracking. (Slice-by-slice, not all at once.) |

## Assumed defaults (unanswered — override anytime)

- **Content visibility (assumed):** per-page `visibility` = public / unlisted / private, **default private on import**. You promote pages to public deliberately. (Health/family/spiritual/tracker content must not auto-publish.)
- **Filament's role (assumed):** Filament = **admin panel** (content, users, courses, analytics, publish gate). A separate **Livewire + Blade + Tailwind** public frontend is the learner-facing site. Cleaner learner UX than exposing the admin panel.
- **Billing (assumed):** **free accounts now, billing-ready later.** No Stripe/Cashier in v1; design enrollment so paid tiers slot in later.
- **Database (assumed):** **SQLite for dev** (zero setup, Laravel 11 default) → **MySQL/Postgres for production.** `mysql` client is already on the machine.
- **i18n (assumed):** English-only v1. (Georgian content exists but defer localization.)

## Stack (as built)

- **Laravel 13.19** (PHP 8.3), **Filament v3.3.54** admin, **Livewire 3.8** + Tailwind public frontend.
- `league/commonmark` 2.8 for Markdown → HTML, with a custom `[[wiki-link]]` inline parser (to build).
- **PHP runs in a podman container** (`Containerfile` → `academy-php` image); Node/Vite native on host. All PHP/Composer/Artisan go through the `./run` wrapper. No system PHP / no sudo.
- DB: SQLite (`database/database.sqlite`) for dev.
- Auth: Laravel Fortify/Breeze; roles learner / editor / admin (to build).
- Importer: Artisan command `wiki:import` that re-implements the Python parsing rules
  (see `tools/para_indexer.py`, `wiki_link_graph.py`, `wiki_page_catalog.py`,
  `wiki_scores.py` in the wiki repo) in PHP.

## Slice 0 — DONE (2026-07-10)

Scaffold boots: Laravel 13 + Filament 3.3 admin panel at `/admin`, SQLite migrated,
admin user `admin@academy.test` / `password`, dev server verified (HTTP 200 on `/` and
`/admin/login`).

## Data model (first cut)

Import target tables (mirror of markdown, regenerated on `wiki:import`):

- `domains` — 10 fixed rows (number = PK, permanent Wheel-of-Life). + level/palace lookup tables (10 levels, 6 palaces).
- `pages` — slug (unique), title, path, palace, level, domain_id, room, para, summary, sources, last_updated, body_md, body_html, **visibility (default private)**.
- `links` — source_slug, target_slug, display_text, anchor, resolved (broken = target with no page).
- `marks` — page_slug, sigil, text, para_index, route (semantic-reading `{{Sigil|...}}`).
- `glossary_terms` — term, owner_slug, section (seed from `glossary-registry.tsv`).
- `unlocks` + `page_unlocks` — SPARK cross-topic syntheses.
- `sources` — one row per `raw/**` + `Clippings/**` file.

Learner/pedagogy tables (later slices): `users`, `courses`, `modules`, `enrollments`,
`skills` (mastery-tree), `drill_attempts`, `sr_cards` + `sr_reviews`, `meter_events`.

## Slice roadmap

0. **Toolchain + scaffold** — install PHP/Composer, `laravel new`, add Filament, SQLite.
1. **Importer + read model** — `wiki:import` parses markdown → `pages`/`links`/etc; Filament resources to browse pages, links, glossary; per-page visibility gate.
2. **KB + admin dashboard** (first user-visible slice) — port `dashboard-scores`: Complexity/Acquirement/Absorbed-% + coverage heatmap by domain·palace·level + graph health (orphans, broken links).
3. **Public frontend + auth** — Livewire reader for *published* pages, working `[[links]]`, backlinks, search, 3-axis nav; signup/login; roles.
4. **Courses + mastery-tree** — roadmaps → courses/modules; `mastery-tree` prereq DAG gates enrollment; per-learner progress.
5. **Gyms** (native rebuild) — port the web-gym engine + one gym end-to-end with METER telemetry.
6. **SRS** — import `*-sr` decks + `reflex-anki` YAML; scheduler + review UI.
7. **METER analytics** — per-learner event log + evaluation engine feeding personal dashboards.

## Slice 1 — DONE (2026-07-10)

Importer + read model + admin browse, verified by `tests/Feature/AdminPanelTest.php`.

- **Snapshot**: `./sync-content.sh` (host rsync) copies `../Neural-OS-Research/wiki` → `content/wiki` (gitignored). Container parses `content/`.
- **`php artisan wiki:import`** parses the snapshot into: `pages` (1,233), `links` (37,901 occurrences; 1,099 broken flagged), `glossary_terms` (1,261), `marks` (170 — exact match to the wiki's `marks-summary.json`), `unlocks` (100) + `page_unlocks` (593). Reference tables `domains`/`palaces`/`levels` seeded from `config/wiki.php`.
  - Frontmatter parsed with `symfony/yaml` + a tolerant line-scanner fallback; `[[wiki-links]]` + slug rules ported from the Python indexers (`app/Support/Slug.php`, `app/Services/Wiki/WikiParser.php`).
  - **Every page imports `private`.** Re-import **preserves** admin visibility choices (upsert excludes the `visibility` column). `--prune` deletes pages whose markdown is gone.
  - 67 pages flagged `axis_clean = false` (missing ≥1 of palace/level/domain) for review.
- **Admin (`/admin`)**: `WikiStatsOverview` dashboard widget (pages / published / links+broken / orphans / glossary / unlocks); `PageResource` (browse, search, filters by visibility·domain·palace·para·orphans·broken-links; **bulk make public/unlisted/private** = the publish gate; markdown fields read-only so re-import never clobbers admin intent); `GlossaryTermResource` (read-only registry, flags missing owner pages).
- **Auth gate**: `User.is_admin` + `canAccessPanel()` — only admins reach `/admin`; learners (later) use the public frontend. Seeded admin `admin@academy.test` promoted to admin. *(Superseded by roles in Slice 2.)*

## Slice 2 — DONE (2026-07-10)

Auth + roles for the multi-user school, verified by `AdminPanelTest` + Breeze's auth suite (34 tests pass).

- **Public auth**: Laravel Breeze (Livewire stack) — `/register`, `/login`, password reset/confirm/update, email verification, `/profile`, and an auth-gated learner `/dashboard` placeholder. Tailwind assets built via native `npm run build` (Node on host; container has no npm).
- **Roles**: `App\Enums\UserRole` (learner/editor/admin) replaces `is_admin` (migration backfills existing admins). `User::canAccessPanel()` admits **staff only** (admin + editor); learners get 403 on `/admin` and use the public site. New sign-ups default to **learner**.
- **User management**: Filament `UserResource` (People group) — admins create/edit users, set roles (badge-colored), filter by role, set/reset passwords (auto-hashed). No more tinker.
- Dev admin `admin@academy.test` auto-migrated to role `admin`.

## Slice 3 — DONE (2026-07-10)

Public learner frontend — the reader for published pages. Verified by `PublicReaderTest` (39 tests pass total).

- **Reader** (Livewire + Tailwind): `/library` (searchable, domain-filtered index of **public** pages) and `/wiki/{slug}` (rendered page). `App\Livewire\Library`, `App\Livewire\ShowPage`, layout `layouts/public.blade.php`.
- **`WikiRenderer`**: strips the metadata preamble + `{{Sigil|…}}` marks, resolves `[[wiki-links]]` to `/wiki/{slug}` **only when the target is viewable** (else plain text), renders GFM markdown with raw HTML escaped (`league/commonmark` `GithubFlavoredMarkdownConverter`). `@tailwindcss/typography` `prose` styling.
- **Backlinks**: each page shows "Linked from" (inbound resolved links from viewable pages).
- **Visibility enforcement**: `public` listed + readable; `unlisted` readable by direct link only; `private` → 404 for guests, staff-preview for logged-in staff (`Page::scopePublic/scopeViewable`).
- **Starter content**: `php artisan wiki:publish-starter` published **694** non-personal technical pages (learning-systems, cybersec, logic, programming, encoders, problem-solving, systems-thinking, graph-theory, networking, math, cross-cutting). Personal domains stay private.
- Known polish item: page titles containing markdown emphasis (e.g. `*The Language Instinct*`) show literal asterisks in the `<h1>` — render title as inline markdown in a later pass.

## Slice 4 — DONE (2026-07-10)

Analytics dashboard in the Filament admin. Verified by `AdminPanelTest` (40 tests pass total).

- **Scores import**: `wiki:import` now also loads `_meta/dashboard-scores.json` into `score_lenses` (Complexity 11,136 · Acquirement 1,207 · Absorbed 10.8%) with per-domain/palace/track lens rows. `App\Models\ScoreLens`.
- **Dashboard widgets** (`/admin`):
  - `WikiStatsOverview` (extended) — Pages, Published, **Complexity, Acquirement, Absorbed-%**, Links (broken/orphans).
  - `DomainScoresChart` — bar chart of complexity by domain (Filament ChartWidget, from `score_lenses`).
  - `CoverageHeatmap` — custom widget: a domain × palace matrix of page counts (DB-native), heat-shaded, with row/column totals.
- Scores are read-only (regenerated by the wiki's own pipeline); coverage/graph-health are computed live from the imported tables.
