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

- **Laravel 13.19** (PHP 8.3), **Filament v3.3.54** admin, **Livewire 3.8** + Tailwind public frontend. (Slice 5.1 adds a single **React 18 island** — Excalidraw — scoped to the course sketchpad; Livewire stays the default everywhere else.)
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

## Slice 5 — DONE (2026-07-10)

Courses + soft mastery-tree — the learning layer on top of the read-only wiki. Verified by `CoursesTest` (49 tests pass total). Two forks locked with the user: **(1) scaffold-from-roadmaps-then-curate**, **(2) soft-gate prerequisites** (surface, don't enforce — real mastery-confirmation waits for METER, Slice 7).

- **Data model** (`courses` → `modules` → `lessons`→page; `course_prerequisites` self-DAG; `enrollments`; `lesson_completions`). Courses are **DB-authored**, not markdown — they are the interactive layer decision #2 reserves for Laravel. Migration `2026_07_10_100006_create_course_tables.php`. Models `Course`/`Module`/`Lesson`/`Enrollment`/`LessonCompletion`; `User` gains `enrollments`/`enrolledCourses`/`lessonCompletions`.
- **Scaffolder**: `php artisan courses:scaffold {roadmap-slug}` reads an imported roadmap page's `body_md`, treats each `##` heading as a module and the `[[links]]` beneath it as lessons (resolving to existing pages; unresolved skipped; prose-only headings dropped). Lands the course **draft**; idempotent with `--force` (rebuilds modules/lessons, keeps status). No arg → lists roadmap candidates. It's deliberately rough (e.g. DSA roadmap yields "Out of scope"/"Related pages" modules) — admins prune it. Course meta (title minus " Roadmap", subtitle from summary, source page, domain) carried over.
- **Admin curation** (`/admin` → Learning → Courses): `CourseResource` — edit course meta, **publish gate** (draft/published bulk actions), domain, prerequisites multiselect (`hiddenOn('create')` — needs a saved course); `ModulesRelationManager` reorders modules and manages lessons via a nested repeater (searchable page picker, optional toggle, reorder).
- **Public learner UI** (Livewire + Tailwind): `/courses` (published-only cards with enrolled/prereq badges + progress), `/courses/{slug}` (`ShowCourse` — enroll, per-lesson complete toggle, progress bar over **required** lessons, soft-prereq list with met/unmet, draft→staff-preview/404), `/my-courses` (enrolled + progress). Completing all required lessons stamps `enrollment.completed_at`; un-toggling clears it. Optional lessons excluded from progress. The wiki reader (`ShowPage`) now shows a **"Part of {course}"** cross-link for any published course using the page as a lesson.
- **Soft gate**: `Course::prerequisitesMetBy($user)` and the UI surface prerequisites as "Recommended first" with met/unmet ticks, but enrollment is never blocked. The hard DAG gate lands when METER (Slice 7) can confirm mastery.
- **First real course** (curated, published): `database/seeders/DsaCourseSeeder.php` — the courses layer's `wiki:publish-starter`. Hand-curated **Data Structures & Algorithms** (`/courses/dsa`): 10 modules, 57 lessons (54 required + a 3-lesson optional Practice module) over the published `programming/` DSA atlas — Foundations & Complexity → Linear DS → Hashing/Heaps → Trees → Sorting/Searching → Design Paradigms → Graph Algorithms → Coding Patterns → Strings/Math/Hardness → drills. Idempotent; drops the rough `dsa-roadmap` scaffold demo when it runs. `./run php artisan db:seed --class=DsaCourseSeeder`.
- Next slices unchanged: Gyms (native rebuild), SRS, METER.

## Slice 5.1 — DONE (2026-07-10)

Per-course **Excalidraw sketchpad** — a real drawing whiteboard inside each course. Locked with the user: **one whiteboard per learner per course**, using **actual Excalidraw** (not a lookalike). Verified by `SketchpadTest` (55 tests pass total) + a live browser round-trip (drew a rectangle → autosaved → reloaded → rehydrated from DB).

- **First React in the app.** Excalidraw is React-only, so `@excalidraw/excalidraw` 0.18 + `react`/`react-dom` 18.3 + `@vitejs/plugin-react` were added as a **React island** — one Vite entry (`resources/js/sketchpad.jsx`) mounted into a plain Blade page (`courses/sketchpad.blade.php`), *not* a Livewire component, so React owns its DOM subtree with no morphdom conflict. `vite.config.js` gains the react plugin + `define: { 'process.env.IS_PREACT': ... }` (Excalidraw reads it at runtime). Livewire/Blade stays the default for everything else; React is scoped to this island.
- **Persistence.** `drawings` table = one row per `(user_id, course_id)`, `scene` = raw Excalidraw `serializeAsJSON` output (elements + appState + files). `SketchpadController@show` embeds the saved scene via `@json` (hex-escapes `</script>`) as `initialData`; `@save` upserts it. Routes are `auth`-gated; published courses open to any learner, drafts staff-only (same visibility rule as the reader). Scene capped at 8 MB, validated as JSON.
- **Autosave.** The island debounces saves (1.5 s) but gates them on Excalidraw's `getSceneVersion` — mount, selection, and viewport-only changes don't write; only real element edits do. Plus an explicit **Save** button + status. Reached via an **✎ Sketchpad** link on the course page (`/courses/{slug}/sketchpad`).
- **Bug fixed en route:** `bootstrap/app.php` only rendered JSON errors for `api/*`, so a validation failure on the JSON save endpoint took the HTML redirect-back path and threw. Widened to `api/* || $request->expectsJson()` (Laravel's own default) — a latent bug that would have hit any AJAX endpoint.
- Bundle note: the sketchpad chunk is ~1.3 MB (Excalidraw pulls mermaid/katex/cytoscape); it loads only on its own page. Excalidraw fonts currently load from unpkg at runtime — self-host via `EXCALIDRAW_ASSET_PATH` if this needs to run offline.

## Slice 6 — DONE (2026-07-10)

Gyms — the wiki's spec-driven web-gym engine, rebuilt natively with **server-side METER telemetry**. Forks locked with the user: **algorithm-pattern gym first** (pairs with the DSA course), **linked to courses now**. Recognition mode only in v1. Verified by `GymTest` (63 tests pass total) + a live browser round-trip (intro → timed round → timeout → feedback with explanation → attempt logged).

- **The model** mirrors the wiki's `web-gym-generation-schema`: `gyms` (mode, target reflex, timer/round config, pass/promote thresholds, `stages` JSON, optional `course_id`) + `gym_items` (prompt, `choices` JSON, correct, explanation, near-miss `detail`). A gym is a timed classification session.
- **Telemetry = the point** (not `localStorage` like the HTML gyms): every play-through writes `gym_sessions` (accuracy, correct/total, median latency, computed `stage_code`) + `gym_attempts` (item, selected, is_correct, `latency_ms`). This is the concrete seed of METER (Slice 7) — per-learner event data server-side.
- **Engine** (`App\Livewire\PlayGym`): intro → prompt → feedback → summary. An **Alpine** timer/stopwatch in the view drives the `timer_seconds` countdown and measures answer latency (prompt-render → click), calling `answer()`/timeout back into Livewire; the core logic (scoring, attempt logging, session finalize, stage read, top-confusion pair) is server-side and directly unit-tested. `start()` is guarded to one session per run (no abandoned sessions from double-clicks). Playing requires auth (telemetry is per-learner); browsing `/gyms` is public.
- **First gym** (`GymSeeder`, published): **Algorithm Pattern Gym** — 20 recognition items ported from `gyms/algorithm-pattern-gym.html`, 8s timer, 5-stage ladder, **linked to `/courses/dsa`**. The DSA course page shows a 🏋 **Practice** link to it, and the gym links back. `./run php artisan db:seed --class=GymSeeder`.
- Public surface: `/gyms` index + `/gyms/{slug}` play, plus a **Gyms** nav item. Execution/retrieval/stress modes and a `gyms:import` command for `.spec.json` gyms are deferred.

## Slice 7 — DONE (2026-07-10)

METER — the measurement layer over the telemetry the gyms/courses now emit, turning "am I improving?" into numbers. Forks locked with the user: **the learner `/dashboard` placeholder becomes the real METER dashboard**; **learner-only/private** (no admin cohort view in v1). Faithful to the wiki's METER spec (`[[meter-overview]]`): unified event schema, pass/floor/target evaluation, N<10 "insufficient signal", trends-not-point-estimates, and no gamification. Verified by `MeterTest` (70 tests pass total) + a live browser round-trip.

- **Unified event log** (`meter_events`): the append-only schema (`layer` · `operation` · `metric_type` · `mode` · `correct` · `latency_ms` · `value` · `context` · `source_key`). One table for all layers — future SRS/PULSE write here too, so cross-layer patterns stay queryable. `source_key` (e.g. `gym_attempt:123`) makes every emit idempotent.
- **Emit** (`App\Support\Meter`): `PlayGym` emits a `performance/gym-rep` per attempt + a `gym-session` summary on finalize; `ShowCourse` emits a `retrieval/lesson-complete` on completion. `meter:backfill` replays existing `gym_attempts`/`gym_sessions`/`lesson_completions` into the log (idempotent via `source_key`), so history collected before live-emit appears.
- **Evaluate + Report** (`App\Services\Meter\Report`): per-gym rolling accuracy + median latency vs floor/working/target → a verdict (Promote-ready / On track / Below target / Needs attention / Insufficient signal), a latency read, the latest session's stage, a per-session accuracy trend, and a 3-session floor-breach flag. Scoped to one user (private).
- **Dashboard** (`DashboardController` → `dashboard.blade`, on the Breeze auth layout — keeps logout/profile): Daily Glance, the performance panel (verdict + accuracy/latency/stage + trend sparkline), course progress, window totals, and a Goodhart "signals, not a scoreboard" prompt. **No streaks/points/leaderboards**, per METER's non-goals.
- Deferred: Weekly/Monthly report generation, calibration suggestions, PULSE state-conditioning, and an admin cohort view.

## Testing (2026-07-10)

Two layers:

- **PHPUnit feature tests** (`./run php artisan test`) — 70 in-process tests: routing, Livewire components, Filament resources/widgets, auth, roles, visibility, wiki-link resolution, the courses layer (scaffolder, enrollment/progress/completion, soft prerequisites, admin curation), the sketchpad (auth gate, save upsert, ownership isolation, draft visibility), the gym engine (session flow, attempt/session telemetry, stage read, course link), and METER (emit on rep/session/lesson, backfill idempotency, verdict thresholds + insufficient-signal, per-user scoping, dashboard render).
- **Playwright smoke suite** (`npm run e2e`, `e2e/*.spec.ts`) — 5 real-browser tests (headless chromium on host Node, reuses the container's server): guest reads a page + follows an internal link, live search filters, private page 404s, admin login + lazy dashboard widgets load, admin pages list renders. Config uses `127.0.0.1` (not `localhost`) so the reuse-probe hits pasta's IPv4 listener.

## Module-tagged gym items — DONE (2026-07-12)

Step 1 of evidence-based module coverage: module completion today is an honor-system lesson checkbox; the plan is a per-module METER verdict over gym telemetry (rolling accuracy ≥ `pass_accuracy` with N ≥ 10, sustained across sessions → rung 4 Classifiable), then gating module completion on it. This slice scopes the instrument to the module. Verified by `GymTest` (74 tests pass total).

- **Schema**: nullable `gym_items.module_id` FK (`nullOnDelete`) — migration `2026_07_12_100000_add_module_id_to_gym_items.php`. Null because untagged items stay gym-wide, and course seeders rebuild modules with fresh IDs (a module delete must untag, not destroy, items).
- **Read side**: `GymItem::module()`, `Module::gymItems()`, and `Module::gymAttempts()` (has-many-through) — the per-module evidence stream a future METER verdict consumes; narrow to one learner via `whereHas('session', …)`.
- **Seeder**: each of the 20 Algorithm Pattern Gym items is tagged with the DSA module it exercises (7 of 10 modules covered — Foundations, Strings/Math & Hardness, and Practice & Retention have no recognition items). Tags resolve by module *title* at seed time, so re-running `GymSeeder` after a DSA course re-seed restores them; `GymTest` covers the churn → re-tag round-trip.
- En route: fixed two `GymTest` assertions left stale by the KnowledgeLadder refactor (expected retired `S1`/`S3` stage codes; sessions now record ladder codes `L0`/`L7`).
- Next steps (deferred): per-module `Report` verdict, module-completion gate in `ShowCourse`, target-rung per module, SRS-based retention evidence.

## Per-module coverage verdict — DONE (2026-07-12)

Step 2 of evidence-based module coverage: METER now reads a coverage verdict per instrumented module. Verified by `MeterTest` (80 tests pass total) + a real-data read on the dev DB (admin's pre-tagging attempts attribute correctly per module; all read "Insufficient signal" at n=6–9, as the N<10 guard demands).

- **`Report::coverage()`** — for each enrolled course, each module with tagged gym items gets an evidence read from the gym-attempt stream (`Module::gymAttempts()`, per-learner via the session, windowed). A module is COVERED only when the claim survives three guards: **n ≥ 10** reps (`MIN_SIGNAL`), across **≥ 2 sessions** (`MIN_SESSIONS`, the sustained/not-one-lucky-run rule), at **accuracy ≥ the instrument gym's `pass_accuracy`** — rung 4 Classifiable or better. The read also carries the existing floor/working/target verdict and the Knowledge Ladder rung (`levelForGym`, latency-aware) once signal is sufficient.
- **Honest about the instrument**: modules with no tagged items get no verdict — they surface as an `uninstrumented` count, never silently covered. Attribution lives on `gym_items.module_id`, so coverage reads attempts directly rather than duplicating module ids into the event log.
- **Dashboard**: a "Module coverage" panel (after Course progress) — per course, each instrumented module with its ✓/verdict chip, n · sessions · accuracy · rung, and the insufficient-signal progress read (`n/10 reps`).
- Next steps (deferred): module-completion gate in `ShowCourse` (checkbox = exposure, gym = knowledge), per-module target rung, SRS retention evidence.

## Module-completion gate — DONE (2026-07-12)

Step 3 of evidence-based module coverage: course completion is now earned, not checked. The lesson checkbox still measures exposure ("I read it"); on instrumented modules the gym evidence decides completion ("I know it"). Verified by `CoursesTest` (84 tests pass total) + a real-data read (admin on `/courses/dsa`: 9 reps on one module, nothing read → both gates correctly shut).

- **`Module::completedBy()`** — every required lesson checked AND, when the module has tagged gym items, `Report::moduleEvidence()['covered']`. Uninstrumented modules complete from checkboxes alone (nothing to measure against — behavior unchanged for every existing course without a gym). `Course::completedBy()` = every module complete; soft prerequisites inherit the gate for free via `completed_at`.
- **`Report::moduleEvidence()` went public static** — the single coverage primitive shared by the dashboard and the gate, so they can never disagree. (`verdict()` became static with it; evidence read gained a `pass` key.)
- **`ShowCourse::syncCompletion()`** routes through the gate, with two deliberate asymmetries: (1) coverage earned in the gym has no lesson-toggle to hook, so the gate is re-checked on every course-page visit (mount) and completion lands then; (2) unchecking a lesson still revokes completion, but evidence aging out of the 30-day METER window does **not** — once earned, completion is sticky against decay (retention is SRS's job, not a reason to silently un-complete a course).
- **Course page UI**: per-module chips name the next missing guard in gate order (`🏋 n/10 reps` → `🏋 one more session` → `🏋 75% — pass is 80%` → `✓ Covered`); when all lessons are read but the gate is open, the header reads "Evidence pending" and a banner names the pending modules with a Practice link.
- Next steps (deferred): per-module target rung, SRS retention evidence, execution/retrieval gym modes for deeper rungs.

## Per-module target rungs — DONE (2026-07-12)

Step 4 of evidence-based module coverage: modules now declare *how well* is well enough. `modules.target_rung` (0–9, default 4 Classifiable = `KnowledgeLadder::DEFAULT_TARGET`) replaces the hardwired pass-accuracy bar. Verified by `CoursesTest`/`MeterTest` (86 tests pass total) + a real-data read (dev DB: Coding Patterns target L7 / achieved L1 → shut; Design Paradigms target L4 / achieved L7, n=12 → covered).

- **The gate generalizes, exactly**: covered = n ≥ 10 · sessions ≥ 2 · **achieved rung ≥ target rung**, where achieved comes from the same `levelForGym()` accuracy+latency mapping every session read uses. At the default target this reduces *identically* to the old accuracy ≥ pass rule (rung ≥ 4 ⟺ accuracy ≥ pass), so untouched modules behave bit-for-bit as before.
- **Honesty at the ceiling**: a target above L7 exceeds what a timed recognition drill can certify — the evidence read reports `certifiable: false` and the UI says "needs a deeper instrument" instead of implying more reps could ever close the gate. These targets become reachable when execution/retrieval gym modes land.
- **Curation**: Filament module form gets a Target-rung select (full ladder with standards) + an L-badge table column (amber above the gym ceiling). `DsaCourseSeeder` curriculum rows accept a trailing int rung; **Coding Patterns is set to L7 Reflexive** — the algorithm-pattern gym exists to train exactly that reflex, so covered there means fast, not merely accurate.
- **UI**: course-page chips are rung-aware (`✓ Covered · L7`, `🏋 L5 — target is L7 Reflexive`, `target L8 — needs a deeper instrument`); dashboard coverage lines show `· target Ln` and the uncertifiable note.
- Next steps (deferred): SRS retention evidence, execution/retrieval gym modes for rungs 2/8/9.

## SRS retention evidence — DONE (2026-07-12)

Step 5 of evidence-based module coverage, and the long-deferred SRS slice's seed: coverage answers "did they learn it", this layer answers "do they still know it". Verified by `SrsTest` (96 tests pass total) + a real backfill on the dev DB (60 historical attempts → 20 cards; per-module retention 50–100%, 2 due; review link resolves).

- **Cards, not decks**: one `srs_cards` row per (learner, gym item) — the SRS reviews the *instrument*, so retention lands on the same module-tagged evidence stream as coverage, with zero new content to author. Scheduling is a plain **Leitner ladder** (`App\Support\Srs`, intervals 1/3/7/14/30/60 days — deliberately inspectable, no hidden ease factors): every exposure reschedules; correct climbs a box, a miss drops to box 1 and counts a lapse; first-exposure misses are not lapses.
- **Review mode shares the gym engine**: `/gyms/{slug}?mode=review` (Livewire `#[Url]`) queues only due cards, oldest first. Review reps log normal gym telemetry *plus* a `retrieval/srs-review` event (`source_key srs_review:{attempt}`); the drill intro nudges toward due cards, and an empty review politely refuses to start.
- **Retention is a signal, not a gate**: `Report` module evidence gains `retention {scheduled, due, rate}`, the Daily Glance gains "reviews due", and dashboard course rows get a `🔁 review N due` link. Deliberately does NOT reopen the coverage gate — completion stays sticky; decay surfaces as due reviews.
- **`srs:backfill`** rebuilds all schedules by replaying attempt history chronologically (rebuild-from-scratch, converges on re-run).
- ⚠ **Hazard surfaced en route**: `DsaCourseSeeder` deletes + recreates the course, which cascade-wipes learner `enrollments` (and lesson completions) — fine while the only learner is the admin, but it must become a true in-place upsert before real learners exist. Not fixed in this slice.
- Next steps (deferred): execution/retrieval gym modes for rungs 2/8/9; make course seeders enrollment-safe.

## Enrollment-safe course seeders — DONE (2026-07-12)

The hazard flagged in the SRS slice, fixed at the root: all four course seeders (DSA, Neural OS, Graph Theory, Comprehension) shared the delete+recreate pattern that cascade-wiped enrollments and lesson completions and churned module IDs. Verified by `CourseSeederSafetyTest` (101 tests pass total) + re-running all four seeders on the dev DB (enrollment, 20 gym tags, 20 SRS cards, course + module IDs all bit-for-bit unchanged).

- **One shared trait**, `Database\Seeders\Concerns\UpsertsCourseCurriculum::upsertCourse()`, replaces each seeder's hand-rolled materialization. Identity keys: **course by slug, modules by title, lessons by page** — lessons are looked up course-wide, so a lesson moved between modules keeps its id and its completions. Whatever leaves the curriculum is pruned (renaming a module is re-authoring — its row goes); flags (`'optional'`, int target rung) update in place.
- **Consequences**: `enrollments`/`lesson_completions` survive re-seeds; gym-item module tags survive too (module IDs stable), so GymSeeder's re-tag-after-reseed ritual is no longer needed — `GymTest` now asserts tags *survive* a course re-seed instead of asserting the churn. DsaCourseSeeder still drops the old `dsa-roadmap` scaffold demo (a different course).
- The seeder remains authoritative over its curriculum: hand-added lessons inside a seeder-managed course are pruned on re-seed, same as before.
- Next steps (deferred): execution/retrieval gym modes for rungs 2/8/9.

## In-course lesson reader + lesson-embedded checks — DONE (2026-07-12)

Courses gained two things: reading a lesson no longer bounces the learner out to the plain wiki reader, and a lesson can carry its own "check your understanding" that feeds the *existing* module-completion evidence gate rather than a new parallel one. Verified by `ShowLessonTest` + `LessonCheckTest` + `GymTest`/`CoursesTest` additions (121 tests pass total) + a live click-through on the dev DB (tagged 2 real `algorithm-pattern-gym` items to the `hash-table` DSA lesson, answered both through the browser, watched `Report::moduleEvidence()` move n=9→11 and `covered` flip, then reverted the fixture).

- **New route** `/courses/{course}/lessons/{lesson}` → `ShowLesson`, replacing `show-course.blade.php`'s per-lesson link to the standalone `/wiki/{slug}` reader. Renders the same `WikiRenderer` output inside the course shell: sidebar (module/lesson list, current lesson highlighted), prev/next across the course's full authored order (`Course::allLessons()`, optional lessons included — prev/next follows the reading sequence, not the required-for-completion subset), and an inline mark-complete control. The standalone `/wiki/{slug}` reader is untouched and still the target for pages read outside a course.
- **Two extractions make this additive, not duplicative**: `App\Livewire\Concerns\ReadsWikiPage` (the visibility guard + `WikiRenderer::render()` + backlinks query, lifted from `ShowPage`) and `App\Livewire\Concerns\TracksCourseProgress` (`enroll()`/`toggleLesson()`/`syncCompletion()`/`refreshProgress()`, lifted from `ShowCourse`) — both `ShowPage`/`ShowCourse` and the new `ShowLesson` mix these in, so the render pipeline and the sticky-completion gate each have exactly one implementation. Matching Blade partials (`partials/page-body.blade.php`, `partials/lesson-nav-list.blade.php`) do the same for the view layer.
- **Lesson-level checks reuse the Gym/METER stack, not a new one**: `gym_items.lesson_id` (nullable, `nullOnDelete`, mirrors the existing `module_id` column exactly) tags an item to a specific lesson *in addition to* its module — every lesson-check item sets both fields, so `Module::gymAttempts()`/`Report::moduleEvidence()` need zero changes to see these reps; `lesson_id` only decides what renders on which lesson page. `App\Support\GymScoring` (extracted from `PlayGym::answer()`/`finalize()`) is the one scorer both the full gym drill and the new embedded `App\Livewire\LessonCheck` call — same `GymAttempt`/`Meter::gymRep`/`Srs::record`/`Meter::gymSession` path either way.
- **`LessonCheck` is deliberately not `PlayGym`**: untimed (self-paced, no countdown), no round-count picker, no Knowledge Ladder summary screen — just plain correct/incorrect feedback, consistent with METER's no-gamification stance. It requires login to start (`gym_sessions.user_id` is non-nullable — a guest attempting the check redirects to `/login` instead of erroring), and renders nothing at all when a lesson has no tagged items (extends the existing "uninstrumented modules complete on checkboxes alone" honesty rule one level down).
- **Gotcha worth naming for future full-page/nested Livewire components**: a `mount()` parameter with the *same name* as a public property (e.g. `mount(string $course)` next to `public Course $course`) crashes or silently no-ops, because Livewire's nested-component initialization auto-assigns matching-named params directly onto same-named properties before `mount()` runs. Fixed by naming route/mount params distinctly from the typed properties they populate (`$courseSlug`/`$lessonSlug`, `$forLesson`) — every full-page component here now follows that convention.
- Next steps (deferred): a Filament UI for tagging gym items to lessons (today it's direct `module_id`/`lesson_id` assignment, same as module tagging before this slice); execution/retrieval gym modes for rungs 2/8/9 (carried over).

## Blind-spot floor on the gym ladder — DONE (2026-08-19)

Ported from the wiki's `gyms/algorithm-pattern-gym.html`, which gained this gate after a real 85%/5.2s run promoted past a Monotonic Stack scored 0-for-2. The school's engine had the same hole: `levelForGym()` read accuracy + median latency only, so 90% across 12 pattern families awarded **L7 Reflexive** while one family sat at zero. Verified by `GymTest` (130 tests pass total) + `e2e/gym.spec.ts` driving 20 real rounds in Chromium, light and dark.

- **A blind spot is a category whose every item in the run was missed.** `GymScoring::blindSpots()` groups a session's attempts by `gym_items.correct` — a classification gym's correct answers *are* its category labels, so the per-category read needs no new tagging, no new column, and no per-gym configuration of what the categories are. Only categories actually drawn are considered, so a `?mode=review` pass is judged on what it asked rather than on the whole deck.
- **The floor caps, never lifts**: `KnowledgeLadder::levelForGym()` gained a 4th argument and `BLIND_SPOT_CEILING = 4`; the accuracy/latency bands moved untouched into a private `bandForGym()`, and the public method is `min(band, ceiling)` when a blind spot is present. Bands at or below 4 are unaffected. The withheld band is exactly the promote band (5 Operational, 7 Reflexive) — the same threshold that reads as "ready for new material".
- **Distribution beats the mean, and the summary says so**: two runs at an identical 90% and identical speed now diverge on *where* the misses landed — concentrated on one family reads L4 with a panel naming the family, its 0-of-n evidence, and the rung the hole cost ("accuracy and speed alone read L7 · Reflexive, withheld to L4"); spread across two families still reads L7. Both are asserted, side by side, in `GymTest` and again in the e2e spec.
- **Stated before the run, not sprung after it**: the intro's config line carries "no zeroed category" beside the rounds/timer/promote figures. `gyms.blind_spot_floor` (default true) lets a gym whose `correct` values are not category labels opt out; `gym_sessions.blind_spots` stores `[{category, items}]` so the summary names the hole instead of just withholding a rung.
- **Deliberately not applied to module coverage**: `Report` still calls `levelForGym()` with three arguments (blind spot false). A module-scoped read scores a *subset* of items where a missing category means nothing, so the coverage gate is bit-for-bit unchanged. Sessions finalized before this slice keep `blind_spots = null` — historical rungs are not retroactively re-scored (the dev DB's three demo sessions have no zeroed family, so none is misreported).
- **Two environment fixes fell out of the e2e work**: `playwright.config.ts` honours `E2E_PORT` (an unrelated container held `:8000`, and `reuseExistingServer` had cheerfully run the suite against *that* app's login page), and `./run` now publishes whatever port `--port=` asks for instead of a hardcoded 8000.
- Next steps (deferred): execution/retrieval gym modes for rungs 2/8/9 (carried over); a per-family trend across sessions, so a blind spot that clears is visibly distinct from one that keeps recurring.

## Restyle: Bookish → Soft Workspace — DONE (2026-08-19)

A visual-language change only; no page, route, copy or information architecture moved. The design system was already fully token-driven, so this is mostly one file: `resources/css/tokens.css`. Verified in a real browser across library · courses · course · gyms · gym summary · wiki reader · dashboard · login · profile · Filament admin, light **and** dark; 130 tests + the gym e2e still pass.

- **Palette**: warm-paper + oxblood → near-white canvas (`#F6F6F3`), pure-white cards, near-black ink, one interactive hue (**grape `#5B3FD6`**), and a candy status family — mint · butter · blush · sky — used as *fills* for cards and chips rather than as thin accents. Separation now comes from fill and whitespace; borders are whisper-thin and shadows nearly absent.
- **Shape and voice**: the radius scale roughly doubled (cards 20px, panels 28px, buttons and chips are pills), and the serif voice is retired — one geometric-humanist sans (**Plus Jakarta Sans**) does display, headings, UI and reading, with headings at 700 and tighter tracking. `--font-serif` / `font-serif` survive as **aliases** pointing at the display face, so no rename sweep was needed; the 22 heading usages were mechanically renamed to `font-display` anyway and bumped 600 → 700.
- **`--color-bar` is a new token, and it exists because of a bug**: the top bar was first built on `surface-inverse`, which by definition flips — so opening dark mode turned the black bar white. A bar must read as a bar in both themes, so it gets its own pair: near-black in light (the page's one piece of hard contrast), one step *above* the canvas in dark (black-on-black is invisible), light foreground in both.
- **Pages that were never tokenized got tokenized**: the METER dashboard, profile, and the Breeze auth pages still carried stock `gray-*`/`indigo-*`/`emerald-50` from the starter kit and would have stayed oxblood-era grey forever. They now follow the theme like everything else, and the shared Breeze components (primary/secondary/danger buttons, text input, nav links) are pills on tokens instead of uppercase tracking-widest.
- **Brand colors follow the brand; categorical ones do not**: `RepresentationBuilder::CENTER_COLOR` and the mermaid root style moved to grape, but `DOMAIN_COLORS` deliberately stayed warm — a domain keeps its color across restyles so a reader recognizes it between diagrams. The constant carries that reasoning inline.
- **Two nits fixed en route**: the pill sweep caught `rounded-md bg-primary-subtle` on a multi-line callout and turned it into a 9999px lozenge (reverted to `rounded-lg`), and domain chips now carry `whitespace-nowrap` — "Personal Growth / Learning" had been wrapping *inside* its pill on the gyms card.
- Not touched: `welcome.blade.php` is still the stock Laravel starter page, unreachable since `/` redirects to `/library`. Left as dead code rather than restyled.

## Color as data — the domain palette — DONE (2026-08-19)

The Soft Workspace restyle landed the *shapes* but kept a one-hue system: grape links on white cards, pastel reserved for status. This slice pushes color into the cards themselves without inventing decoration — every hue on screen now encodes something. Verified light and dark across courses, course page, gyms, gym intro/summary, dashboard, library and wiki reader; 130 tests + all 8 e2e specs pass.

- **Ten hues, one owner**: `--color-domain-1…10` (pastel fill + a paired deep `-fg`, authored for both themes) sit in tokens.css, and `App\Support\Palette` is the *only* thing that decides which one a subject gets. Domains own a hue **by id**, so a rename never moves a color and the library, a course card and a page header all agree at a glance. The class strings in Palette are literal and the file is in Tailwind's `content` globs — `"bg-domain-{$n}"` would compile to nothing.
- **Where it shows**: domain chips everywhere; tinted heads on course and gym cards (`Palette::nth($model->id)` — keyed to the ID so a card keeps its color through any re-sort); tinted module headers on a course page (the DSA page is now ten colors down the stack); five fixed hues on the dashboard's Daily Glance, one per metric, because a tile's color is part of how you find it.
- **The Knowledge Ladder became a ramp**: climbed rungs take their own hue instead of ten identical grape bars, so height *and* color carry the level. With no session to report the whole strip shows its pastels — the ramp is legible before anyone has climbed it; once there is a level, unreached rungs go grey to say "not yet".
- **What deliberately did NOT get color**: the library's rows are ~800 Personal Growth pages, so tinting the row would paint the page one hue and imply variety that isn't there — the chip carries the domain and stops. `RepresentationBuilder::DOMAIN_COLORS` still holds its own older warm set (see the previous slice); unifying the two is a follow-up, not a silent edit.
- **Hit the documented Blade trap again**: `dashboard.blade.php` uses `@php … @endphp` block form, and the inline `@php(…)` added here got paired by Blade's non-greedy regex with a *later* `@endphp` — ParseError at the inline's line. The rule stands: never mix the two forms in one file. Screenshot review caught it; no test covers dashboard rendering.

## Linux: Bash Scripting — a course, and the source pages it needed — DONE (2026-08-19)

The fifth course, and the first one whose lesson pages did not exist yet. Courses here are curricula over *published wiki pages*, and the wiki had no shell content at all — only cybersec-adjacent Linux pages — so the work was two layers: author the source cluster in the canonical wiki, then seed the course and its gym over it. Verified by `LinuxBashCourseTest` (136 tests pass total) + a live read of `/courses/linux-bash`, a lesson page, and the wiki reader.

- **33 wiki pages, authored in `Neural-OS-Research/wiki/linux/`** and rooted at `bash-atlas`: 30 concept pages across eight dependency-ordered blocks, plus `bash-pitfalls-catalog` (failure-first reverse index) and `bash-drill-ladder` (the recognition ladder). They live in the canonical repo because `./sync-content.sh` runs `rsync --delete` into `content/wiki` — anything authored here would be erased on the next sync. New index area (13th), `wiki/index/linux-and-shell.md`, plus a `log.md` entry, per that repo's rules.
- **The course has a spine, and the module order enforces it**: *a shell line is not executed, it is expanded and then executed*. Module 1 is deliberately short (three lessons) because it exists to establish `expansion-order`; module 2 (quoting/expansion) is where the real difficulty is and carries **target rung 7 Reflexive** — the same call as DSA's Coding Patterns, for the same reason. Knowing the quoting rule and still writing `rm $file` is the failure the course exists to prevent, so covered there means *fast*, not merely accurate.
- **`BashPatternGymSeeder`** — 31 items over **twelve defect families** (`unquoted expansion`, `subshell state loss`, `exit status ignored`, `redirection order`, …). `blind_spot_floor` is on, so the families *are* the categories a run is judged by; every family therefore carries ≥2 items, asserted by a test, because a one-item family gets zeroed by a single unlucky miss and would cap the rung for noise rather than for a real hole. **"Correct as written" is a deliberate thirteenth answer** present in five items: without it the drill teaches "always find a bug", which is its own failure mode in review.
- **Every gym item is tagged to both a module and a lesson**, so the bank feeds `Report::moduleEvidence()` *and* renders as an in-lesson check. `LinuxBashCourseTest` asserts the invariant that `gym_items.lesson_id`'s parent module equals `gym_items.module_id` — and **caught two real mistakes doing so** (a pipeline item filed under Processes when its lesson lives under Files; the `sed -i` portability item filed under Writing-scripts when its lesson lives under The-text-pipeline). Eight of nine modules are instrumented; the optional Reference-and-practice tail is not, which the existing coverage read reports honestly as uninstrumented rather than silently covered.
- **A real renderer bug fell out of the content and had to be fixed first.** `WikiRenderer::linkify()` and `WikiParser::links()` both regex `[[…]]` over the *raw* markdown, before CommonMark sees it. That is fine until a page's subject matter *is* double-bracket syntax: `test-and-double-bracket.md` is wall-to-wall `[[ -f $f ]]`, and every code sample was being stripped of its brackets while the link graph filled with slugs like `f-f`. New `App\Support\MarkdownCode` masks fenced blocks and inline code spans around both call sites; two tests in `PublicReaderTest` pin it. Indented code blocks are deliberately *not* masked — four-space indentation is ambiguous with list continuation in this corpus. The same false positive existed in the wiki repo's own `wiki-precommit-lint.py`; it got the matching fix, so the 33 new pages lint clean rather than emitting 28 permanent phantom-link warnings.
- **`linux` added to `WikiPublishStarter::SAFE_DIRS`** (reference pages, no personal content). The publish step itself was scoped by hand to `linux/%` for this slice: a full `wiki:publish-starter` run would also have published six unrelated pages that arrived in `learning-systems/` and `cybersec/` with the same sync, and making pages public is outward-facing enough to be the user's call, not a side effect.
- Deliberately not done: no drill-ladder or SRS deck content beyond the two reference pages (the gym already writes SRS cards per item); no `sh`/POSIX-only variant of the course; the six pending pages in other SAFE_DIRS are left private.
