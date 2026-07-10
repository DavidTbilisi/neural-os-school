# Neural OS School

A multi-user public web school built from the [`Neural-OS-Research`](../Neural-OS-Research)
markdown wiki and its learning system (METER, drill ladders, encoders, memory palaces,
spaced repetition, gyms).

The wiki stays canonical — this app **imports** `../Neural-OS-Research/wiki/**/*.md`
read-only and adds the interactive/learning layer on top.

See [`docs/decisions.md`](docs/decisions.md) for the locked architecture decisions and
the slice roadmap.

## Stack

Laravel 13 · Filament v3.3 (admin) · Livewire 3 + Tailwind (public frontend) · SQLite (dev) → MySQL/Postgres (prod).

PHP runs in a **podman container** (no system PHP needed); Node/Vite run natively on the host.

## Setup

### PHP toolchain — containerized (no sudo)

The `Containerfile` builds a PHP 8.3 image with every extension Laravel + Filament need.
Build it once:

```
podman build -t academy-php -f Containerfile .
```

Then run **all** PHP/Composer/Artisan commands through the `./run` wrapper (mounts the
project, maps port 8000):

```
./run composer install
./run php artisan migrate
./run php artisan serve --host=0.0.0.0 --port=8000   # → http://localhost:8000
```

### Admin panel

- URL: http://localhost:8000/admin/login
- Dev login: `admin@academy.test` / `password`  (change this)

### Import the wiki

```
./sync-content.sh                # host: copy ../Neural-OS-Research/wiki -> content/wiki
./run php artisan wiki:import    # parse the snapshot into the DB (re-run any time)
```

`wiki:import` imports every page as **private**; promote pages to public in the admin
(Pages → select → "Make public"). Re-imports preserve your visibility choices.
Use `--prune` to drop pages whose markdown was deleted.

## Tests

```
./run php artisan test        # PHPUnit feature tests (in-process: routing, Livewire, auth, widgets)
npm run e2e                    # Playwright browser smoke tests (real chromium against localhost:8000)
```

Playwright runs on host Node and reuses the running dev server (or starts one via `./run`).
First time: `npx playwright install chromium`.
