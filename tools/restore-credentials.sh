#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/credentials-common.sh
source "$SCRIPT_DIR/credentials-common.sh"

ACTIVE_DIR="$(get_credentials_dir)"
BACKUP_DIR="$(get_backup_dir)"
SNAPSHOT_DIR="$(get_snapshot_dir)"
LOG_FILE="$(get_log_dir)/restore-credentials.log"

DEVICE_FILTER=""
BACKUP_PATH=""
DRY_RUN=0

while [ "$#" -gt 0 ]; do
    case "$1" in
        --device)
            DEVICE_FILTER="${2:-}"
            shift 2
            ;;
        --backup)
            BACKUP_PATH="${2:-}"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

if [ -n "$DEVICE_FILTER" ] && ! is_valid_device_name "$DEVICE_FILTER"; then
    echo "Invalid device name: $DEVICE_FILTER" >&2
    exit 1
fi

if ! acquire_lock "chatsmart-wa-restore"; then
    log_message "$LOG_FILE" "Restore skipped because another backup/restore job is already running."
    exit 0
fi

ensure_directory "$ACTIVE_DIR"
ensure_directory "$BACKUP_DIR"
ensure_directory "$SNAPSHOT_DIR"

if [ -z "$BACKUP_PATH" ]; then
    BACKUP_PATH="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'credentials_*.tar.gz' | sort | tail -n 1)"
fi

if [ -z "$BACKUP_PATH" ] || [ ! -f "$BACKUP_PATH" ]; then
    log_message "$LOG_FILE" "ERROR: No valid backup archive found."
    exit 1
fi

log_message "$LOG_FILE" "Starting credentials restore from $BACKUP_PATH"

STAGING_DIR="$(mktemp -d "${TMPDIR:-/tmp}/chatsmart-wa-restore.XXXXXX")"
trap 'rm -rf "$STAGING_DIR"; release_lock' EXIT INT TERM
tar -xzf "$BACKUP_PATH" -C "$STAGING_DIR"

collect_staged_devices() {
    local stage_dir="$1"

    if [ -d "$stage_dir/devices" ]; then
        list_device_dirs_with_state "$stage_dir/devices"
        return 0
    fi

    find "$stage_dir" -type f -name 'creds.json' | while IFS= read -r creds_file; do
        local candidate_dir candidate_name
        candidate_dir="$(dirname "$creds_file")"
        candidate_name="$(basename "$candidate_dir")"
        if is_valid_device_name "$candidate_name" && device_dir_has_state "$candidate_dir"; then
            printf '%s\n' "$candidate_dir"
        fi
    done
}

mapfile -t STAGED_DEVICE_DIRS < <(collect_staged_devices "$STAGING_DIR" | sort -u)

if [ "${#STAGED_DEVICE_DIRS[@]}" -eq 0 ]; then
    log_message "$LOG_FILE" "ERROR: Backup archive does not contain any valid device session."
    exit 1
fi

SELECTED_SOURCE_DIRS=()
for SOURCE_DIR in "${STAGED_DEVICE_DIRS[@]}"; do
    SOURCE_NAME="$(basename "$SOURCE_DIR")"
    if [ -z "$DEVICE_FILTER" ] || [ "$SOURCE_NAME" = "$DEVICE_FILTER" ]; then
        SELECTED_SOURCE_DIRS+=("$SOURCE_DIR")
    fi
done

if [ "${#SELECTED_SOURCE_DIRS[@]}" -eq 0 ]; then
    log_message "$LOG_FILE" "ERROR: Requested device not found in backup: ${DEVICE_FILTER:-all}"
    exit 1
fi

CURRENT_STATE_DIRS=()
mapfile -t CURRENT_STATE_DIRS < <(list_device_dirs_with_state "$ACTIVE_DIR")
if [ "${#CURRENT_STATE_DIRS[@]}" -gt 0 ]; then
    SNAPSHOT_STAGE="$(mktemp -d "${TMPDIR:-/tmp}/chatsmart-wa-snapshot.XXXXXX")"
    mkdir -p "$SNAPSHOT_STAGE/devices"
    CURRENT_DEVICE_NAMES=()
    for CURRENT_DIR in "${CURRENT_STATE_DIRS[@]}"; do
        CURRENT_NAME="$(basename "$CURRENT_DIR")"
        CURRENT_DEVICE_NAMES+=("$CURRENT_NAME")
        cp -a "$CURRENT_DIR" "$SNAPSHOT_STAGE/devices/$CURRENT_NAME"
    done

    write_manifest "$SNAPSHOT_STAGE/manifest.txt" "$ACTIVE_DIR" "${CURRENT_DEVICE_NAMES[@]}"
    SNAPSHOT_PATH="$SNAPSHOT_DIR/current_$(timestamp_now).tar.gz"
    tar -czf "$SNAPSHOT_PATH" -C "$SNAPSHOT_STAGE" .
    rm -rf "$SNAPSHOT_STAGE"
    log_message "$LOG_FILE" "Current active sessions snapshot created: $SNAPSHOT_PATH"
fi

for SOURCE_DIR in "${SELECTED_SOURCE_DIRS[@]}"; do
    DEVICE_NAME="$(basename "$SOURCE_DIR")"
    TARGET_DIR="$ACTIVE_DIR/$DEVICE_NAME"

    if [ "$DRY_RUN" -eq 1 ]; then
        log_message "$LOG_FILE" "DRY RUN: would restore $DEVICE_NAME into $TARGET_DIR"
        continue
    fi

    sync_directory_contents "$SOURCE_DIR" "$TARGET_DIR"
    log_message "$LOG_FILE" "Device restored successfully: $DEVICE_NAME"
done

if [ "$DRY_RUN" -eq 1 ]; then
    log_message "$LOG_FILE" "Restore dry run completed successfully."
else
    log_message "$LOG_FILE" "Restore completed successfully."
fi
