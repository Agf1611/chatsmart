#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/credentials-common.sh
source "$SCRIPT_DIR/credentials-common.sh"

ACTIVE_DIR="$(get_credentials_dir)"
BACKUP_DIR="$(get_backup_dir)"
LOG_FILE="$(get_log_dir)/backup-credentials.log"
KEEP_BACKUPS="${KEEP_BACKUPS:-10}"

if ! acquire_lock "chatsmart-wa-backup"; then
    log_message "$LOG_FILE" "Backup skipped because another backup/restore job is already running."
    exit 0
fi

log_message "$LOG_FILE" "Starting credentials backup from $ACTIVE_DIR"

if [ ! -d "$ACTIVE_DIR" ]; then
    log_message "$LOG_FILE" "WARNING: Credentials directory not found: $ACTIVE_DIR"
    exit 0
fi

mapfile -t DEVICE_DIRS < <(list_device_dirs_with_state "$ACTIVE_DIR")

if [ "${#DEVICE_DIRS[@]}" -eq 0 ]; then
    log_message "$LOG_FILE" "WARNING: No valid device sessions found to back up."
    exit 0
fi

ensure_directory "$BACKUP_DIR"

TIMESTAMP="$(timestamp_now)"
STAGING_DIR="$(mktemp -d "${TMPDIR:-/tmp}/chatsmart-wa-backup.XXXXXX")"
trap 'rm -rf "$STAGING_DIR"; release_lock' EXIT INT TERM

mkdir -p "$STAGING_DIR/devices"

DEVICE_NAMES=()
for DEVICE_DIR in "${DEVICE_DIRS[@]}"; do
    DEVICE_NAME="$(basename "$DEVICE_DIR")"
    DEVICE_NAMES+=("$DEVICE_NAME")
    cp -a "$DEVICE_DIR" "$STAGING_DIR/devices/$DEVICE_NAME"
done

write_manifest "$STAGING_DIR/manifest.txt" "$ACTIVE_DIR" "${DEVICE_NAMES[@]}"

BACKUP_NAME="credentials_${TIMESTAMP}.tar.gz"
BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
tar -czf "$BACKUP_PATH" -C "$STAGING_DIR" .

mapfile -t BACKUP_FILES < <(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'credentials_*.tar.gz' | sort)
if [ "${#BACKUP_FILES[@]}" -gt "$KEEP_BACKUPS" ]; then
    REMOVE_COUNT=$(( ${#BACKUP_FILES[@]} - KEEP_BACKUPS ))
    for (( i=0; i<REMOVE_COUNT; i++ )); do
        rm -f "${BACKUP_FILES[$i]}"
    done
fi

log_message "$LOG_FILE" "Backup created successfully: $BACKUP_PATH (devices: ${DEVICE_NAMES[*]})"
