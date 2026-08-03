#!/usr/bin/env bash
# Copy a snapshot of the canonical wiki into the project's content/ (host-side, no PHP).
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

# --- french-music-drill: stage song units as wiki pages (course: french-through-song) ---
# WikiImport derives slugs from FILENAMES, so units are staged under a `fr-` prefix to keep
# the global slug namespace safe for the other planned drill languages (english-music-drill
# already ships unitNN-* names). The rsync --delete above wipes content/wiki/french-song/
# each run, so this copy is always rebuilt fresh — removed/renamed units clean up themselves.
# Excluded: build plumbing + unvalidated drafts (see tools/french-music-drill/COURSE-MANIFEST.md).
DRILL="$SRC/tools/french-music-drill"
if [ -d "$DRILL" ]; then
  mkdir -p content/wiki/french-song
  staged=0
  for f in "$DRILL"/*.md; do
    case "$(basename "$f")" in
      CURRICULUM.md|RECIPE.md|README.md|COURSE-MANIFEST.md|mnemonics-units-1-3.md|vocab-batch01.md) continue ;;
    esac
    cp "$f" "content/wiki/french-song/fr-$(basename "$f")"
    staged=$((staged+1))
  done
  echo "staged $staged french-music-drill units -> content/wiki/french-song/ (fr- slug prefix)"
fi

# --- audio: stage the MP3 trees into public/audio/ (git-ignored, served statically) ---
# Copies, not symlinks: the podman container mounts only $PWD, so links pointing out of
# the repo would dangle inside it. ~40 MB total. Layout consumed by App\Support\PageAudio
# (page slug fr-<unit> maps back to the drill's plain <unit> dir names) and, later, by
# the gym ports (gyms/data/*.json audioBase).
mkdir -p public/audio
rsync -a --delete "$SRC/tools/french-music-drill/audio/" public/audio/french-drill/
rsync -a --delete "$SRC/gyms/audio/" public/audio/gyms/
echo "staged audio: french-drill + gyms -> public/audio/ ($(find public/audio -name '*.mp3' | wc -l) mp3s)"

# Optional third tree: the curated Suno song takes. The repo holds only TTS reference
# audio — the songs themselves live outside git (repo bloat + Obsidian vault scanning).
# Drop the chosen take per unit as <unit-slug>.mp3 (e.g. unit01-etre.mp3) into
# ~/Music/french-song (or point FRENCH_SONGS_DIR elsewhere) and re-run this script.
SONGS="${FRENCH_SONGS_DIR:-$HOME/Music/french-song}"
if [ -d "$SONGS" ]; then
  rsync -a --delete "$SONGS/" public/audio/french-song/
  echo "staged songs: $SONGS -> public/audio/french-song/ ($(find public/audio/french-song -name '*.mp3' | wc -l) mp3s)"
else
  echo "no Suno takes staged ($SONGS not found) — lesson pages fall back to the TTS render"
fi
