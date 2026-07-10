#!/usr/bin/env bash
# Copy a snapshot of the canonical wiki into academy/content/ (host-side, no PHP).
# The markdown repo stays canonical; this is a read-only import staging copy.
# Re-run any time to re-sync, then: ./run php artisan wiki:import
#
#   ./sync-content.sh                       # default source ../Neural-OS-Research
#   ./sync-content.sh /path/to/Neural-OS-Research
set -euo pipefail
cd "$(dirname "$0")"

SRC="${1:-../Neural-OS-Research}"
[ -d "$SRC/wiki" ] || { echo "ERROR: $SRC/wiki not found" >&2; exit 1; }

mkdir -p content
rsync -a --delete --exclude '.git' "$SRC/wiki/" content/wiki/

echo "synced $SRC/wiki -> content/wiki ($(find content/wiki -name '*.md' | wc -l) markdown files)"
