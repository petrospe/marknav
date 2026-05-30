#!/usr/bin/env bash
# Regenerate data/files.json by scanning the data/ folder for .md files.
# Run this whenever you add, rename, or remove markdown files.
#
# Usage:
#   ./generate-manifest.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
DATA_DIR="$ROOT_DIR/data"
OUTPUT="$DATA_DIR/files.json"

if [ ! -d "$DATA_DIR" ]; then
  echo "Error: $DATA_DIR does not exist" >&2
  exit 1
fi

mapfile -t MD_FILES < <(cd "$DATA_DIR" && find . -type f -name '*.md' \
  ! -name 'files.json' \
  | sed 's|^\./||' \
  | sort)

{
  echo "{"
  echo "  \"generatedAt\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\","
  echo "  \"files\": ["
  total=${#MD_FILES[@]}
  for i in "${!MD_FILES[@]}"; do
    file="${MD_FILES[$i]}"
    if [ "$((i + 1))" -lt "$total" ]; then
      echo "    \"$file\","
    else
      echo "    \"$file\""
    fi
  done
  echo "  ]"
  echo "}"
} > "$OUTPUT"

echo "Wrote $OUTPUT (${#MD_FILES[@]} files)"
