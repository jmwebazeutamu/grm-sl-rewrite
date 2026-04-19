#!/usr/bin/env bash
#
# Run this on your DEV machine to package the running container's SQLite
# database + attachments into a single tarball for transfer to production.
#
# USAGE:
#   ./snapshot-dev-db.sh [output-file]
#
# Default output: /tmp/grm-sl-snapshot-<timestamp>.tar.gz

set -euo pipefail

CONTAINER="${CONTAINER:-grm-sl}"
OUT="${1:-/tmp/grm-sl-snapshot-$(date +%Y%m%d-%H%M%S).tar.gz}"
TMP=$(mktemp -d)

trap 'rm -rf "$TMP"' EXIT

echo "→ Staging DB and storage from container '$CONTAINER'..."
mkdir -p "$TMP/database" "$TMP/storage"

# Use sqlite .backup for a consistent copy even if the app is running
docker exec "$CONTAINER" sqlite3 /app/database/database.sqlite ".backup /tmp/db-snapshot.sqlite" \
  2>/dev/null \
  && docker cp "$CONTAINER:/tmp/db-snapshot.sqlite" "$TMP/database/database.sqlite" \
  && docker exec "$CONTAINER" rm -f /tmp/db-snapshot.sqlite \
  || docker cp "$CONTAINER:/app/database/database.sqlite" "$TMP/database/database.sqlite"

# Attachments
if docker exec "$CONTAINER" test -d /app/storage/app/public; then
  docker cp "$CONTAINER:/app/storage/app/public/." "$TMP/storage/"
fi

echo "→ Packing → $OUT"
tar -czf "$OUT" -C "$TMP" database storage

ls -lh "$OUT"
echo
echo "Next steps:"
echo "  scp $OUT user@<prod-ip>:/tmp/"
echo "  ssh user@<prod-ip>"
echo "  cd /opt/grm-sl-rewrite/deploy && sudo ./deploy.sh --snapshot=/tmp/$(basename "$OUT")"
