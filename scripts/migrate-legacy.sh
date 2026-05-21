#!/usr/bin/env bash
# =============================================================================
# Parallel legacy data migration script
#
# Usage:
#   bash scripts/migrate-legacy.sh [source] [workers] [chunk]
#
# Arguments:
#   source   — all | informal | formal   (default: all)
#   workers  — number of parallel processes (default: 4)
#   chunk    — rows per chunk (default: 500)
#
# Examples:
#   bash scripts/migrate-legacy.sh              # all sources, 4 workers
#   bash scripts/migrate-legacy.sh informal 8   # informal only, 8 workers
#   bash scripts/migrate-legacy.sh formal 2 200 # formal only, 2 workers, 200 per chunk
#
# Prerequisites:
#   - Run `php artisan legacy:migrate --only=reference` first (single-threaded)
#   - Run `php artisan legacy:migrate --only=pins` next (single-threaded)
#   - Then run this script for enrollees
# =============================================================================

set -euo pipefail

SOURCE="${1:-all}"
WORKERS="${2:-4}"
CHUNK="${3:-500}"
ARTISAN="php artisan"
DRY_RUN="${DRY_RUN:-}"   # set DRY_RUN=1 to preview without writing

if [[ -n "$DRY_RUN" ]]; then
    DRY_FLAG="--dry-run"
else
    DRY_FLAG=""
fi

echo "=== Legacy Migration Script ==="
echo "  Source  : $SOURCE"
echo "  Workers : $WORKERS"
echo "  Chunk   : $CHUNK"
echo "  Dry run : ${DRY_RUN:-no}"
echo ""

# ── Helper: get total count for a source table ──────────────────────────────
get_total() {
    local source="$1"
    $ARTISAN tinker --no-interaction <<PHP 2>/dev/null | tail -1
use Illuminate\Support\Facades\DB;
\$tables = match ('$source') {
    'informal' => ['tbl_enrolee'],
    'formal'   => [DB::connection('legacy_mysql')->getSchemaBuilder()->hasTable('tbl_enrolee_formal2') ? 'tbl_enrolee_formal2' : 'tbl_enrolee_formal'],
    default    => ['tbl_enrolee', DB::connection('legacy_mysql')->getSchemaBuilder()->hasTable('tbl_enrolee_formal2') ? 'tbl_enrolee_formal2' : 'tbl_enrolee_formal'],
};
echo collect(\$tables)->sum(fn(\$t) => DB::connection('legacy_mysql')->table(\$t)->count());
PHP
}

# ── Step 1: reference data (single process) ─────────────────────────────────
echo ">>> [1/3] Migrating reference data (single process)..."
$ARTISAN legacy:migrate --only=reference $DRY_FLAG
echo ""

# ── Step 2: pins + invoices (single process) ────────────────────────────────
echo ">>> [2/3] Migrating pins and invoices (single process)..."
$ARTISAN legacy:migrate --only=pins $DRY_FLAG
echo ""

# ── Step 3: enrollees (parallel) ────────────────────────────────────────────
echo ">>> [3/3] Migrating enrollees in parallel ($WORKERS workers)..."

TOTAL=$(get_total "$SOURCE")
if [[ -z "$TOTAL" || "$TOTAL" -eq 0 ]]; then
    echo "No enrollees found. Exiting."
    exit 0
fi

PER_WORKER=$(( (TOTAL + WORKERS - 1) / WORKERS ))
echo "  Total enrollees : $TOTAL"
echo "  Per worker      : $PER_WORKER"
echo ""

PIDS=()
WORKER=1
OFFSET=1

while [[ $OFFSET -le $TOTAL ]]; do
    FROM_ID=$OFFSET
    LIMIT=$PER_WORKER

    echo "  Spawning worker $WORKER: --from-id=$FROM_ID --limit=$LIMIT"
    $ARTISAN legacy:migrate \
        --only=enrollees \
        --source="$SOURCE" \
        --from-id="$FROM_ID" \
        --limit="$LIMIT" \
        --chunk="$CHUNK" \
        $DRY_FLAG \
        >> "storage/logs/legacy-worker-${WORKER}.log" 2>&1 &
    PIDS+=($!)

    OFFSET=$(( OFFSET + PER_WORKER ))
    WORKER=$(( WORKER + 1 ))
done

echo ""
echo "Waiting for ${#PIDS[@]} worker(s) to finish..."
FAILED=0
for PID in "${PIDS[@]}"; do
    if ! wait "$PID"; then
        FAILED=$(( FAILED + 1 ))
    fi
done

echo ""
if [[ $FAILED -gt 0 ]]; then
    echo "=== Migration finished with $FAILED failed worker(s). Check storage/logs/legacy-worker-*.log ==="
    exit 1
else
    echo "=== Migration completed successfully. ==="
fi
