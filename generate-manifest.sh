#!/usr/bin/env bash
# Regenerate data/files.json and images/files.json by scanning the
# respective folders. Run this whenever you add, rename, or remove
# markdown files or images.
#
# Usage:
#   ./generate-manifest.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"

build_manifest() {
  local dir="$1"        # absolute path to the folder being scanned
  local pattern="$2"    # find -name pattern (e.g. '*.md' or expression)
  local label="$3"      # human label for the output line
  local output="$dir/files.json"

  if [ ! -d "$dir" ]; then
    echo "Skipping $label: $dir does not exist" >&2
    return 0
  fi

  # shellcheck disable=SC2086
  mapfile -t MATCHES < <(cd "$dir" && eval "find . -type f \\( $pattern \\) ! -name 'files.json'" \
    | sed 's|^\./||' \
    | sort)

  {
    echo "{"
    echo "  \"generatedAt\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\","
    echo "  \"files\": ["
    total=${#MATCHES[@]}
    for i in "${!MATCHES[@]}"; do
      file="${MATCHES[$i]}"
      if [ "$((i + 1))" -lt "$total" ]; then
        echo "    \"$file\","
      else
        echo "    \"$file\""
      fi
    done
    echo "  ]"
    echo "}"
  } > "$output"

  echo "Wrote $output (${#MATCHES[@]} $label)"
}

build_manifest "$ROOT_DIR/data" "-name '*.md'" "markdown files"
build_manifest "$ROOT_DIR/images" \
  "-iname '*.png' -o -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.gif' -o -iname '*.webp' -o -iname '*.svg' -o -iname '*.avif'" \
  "images"
