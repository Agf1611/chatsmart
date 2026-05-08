#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=tools/credentials-common.sh
source "$SCRIPT_DIR/credentials-common.sh"

ACTIVE_DIR="$(get_credentials_dir)"
LOG_FILE="$(get_log_dir)/credentials-monitor.log"
MONITOR_STATE_DIR="$(get_monitor_state_dir)"
RESTORE_SCRIPT="$SCRIPT_DIR/restore-credentials.sh"
AUTO_RESTORE="${AUTO_RESTORE:-0}"
RESTORE_COOLDOWN_SECONDS="${MONITOR_RESTORE_COOLDOWN_SECONDS:-21600}"
RECENT_CHANGE_GRACE_SECONDS="${MONITOR_RECENT_CHANGE_GRACE_SECONDS:-900}"

ensure_directory "$MONITOR_STATE_DIR"

state_file_for_device() {
    printf '%s/%s.state\n' "$MONITOR_STATE_DIR" "$1"
}

read_state_value() {
    local state_file="$1"
    local key="$2"

    [ -f "$state_file" ] || return 0

    grep -E "^${key}=" "$state_file" 2>/dev/null | tail -n 1 | cut -d '=' -f 2-
}

write_state_file() {
    local state_file="$1"
    local last_issue="$2"
    local last_attempt_epoch="$3"
    local last_success_epoch="$4"

    {
        printf 'last_issue=%s\n' "$last_issue"
        printf 'last_attempt_epoch=%s\n' "$last_attempt_epoch"
        printf 'last_success_epoch=%s\n' "$last_success_epoch"
        printf 'updated_at=%s\n' "$(date -Iseconds)"
    } > "$state_file"
}

clear_issue_state() {
    local device_name="$1"
    local state_file
    local last_success_epoch

    state_file="$(state_file_for_device "$device_name")"
    last_success_epoch="$(date +%s)"
    write_state_file "$state_file" "healthy" "" "$last_success_epoch"
}

if ! acquire_lock "chatsmart-wa-monitor"; then
    log_message "$LOG_FILE" "Monitor skipped because a previous monitor run is still active."
    exit 0
fi

log_message "$LOG_FILE" "Checking credentials status in $ACTIVE_DIR"

if [ ! -d "$ACTIVE_DIR" ]; then
    log_message "$LOG_FILE" "ERROR: Credentials directory not found: $ACTIVE_DIR"
    exit 1
fi

mapfile -t EXPECTED_DEVICES < <(get_expected_devices_from_database | sort -u)

if [ "${#EXPECTED_DEVICES[@]}" -eq 0 ]; then
    mapfile -t EXPECTED_DEVICES < <(list_candidate_device_dirs "$ACTIVE_DIR" | xargs -r -n1 basename | sort -u)
fi

if [ "${#EXPECTED_DEVICES[@]}" -eq 0 ]; then
    log_message "$LOG_FILE" "WARNING: No expected device sessions found to monitor."
    exit 0
fi

HAS_ISSUES=0
NOW_EPOCH="$(date +%s)"

for DEVICE_NAME in "${EXPECTED_DEVICES[@]}"; do
    TARGET_DIR="$ACTIVE_DIR/$DEVICE_NAME"
    STATE_FILE="$(state_file_for_device "$DEVICE_NAME")"

    if device_dir_has_state "$TARGET_DIR"; then
        FILE_COUNT="$(find "$TARGET_DIR" -maxdepth 1 -type f | wc -l | tr -d ' ')"
        log_message "$LOG_FILE" "Credentials OK for $DEVICE_NAME ($FILE_COUNT files)"
        clear_issue_state "$DEVICE_NAME"
        continue
    fi

    HAS_ISSUES=1

    if [ ! -d "$TARGET_DIR" ]; then
        ISSUE_REASON="missing_directory"
        log_message "$LOG_FILE" "ERROR: Device session directory missing: $DEVICE_NAME"
    else
        ISSUE_REASON="incomplete_session"
        log_message "$LOG_FILE" "ERROR: Device session looks incomplete: $DEVICE_NAME"
    fi

    if [ "$AUTO_RESTORE" != "1" ]; then
        write_state_file "$STATE_FILE" "$ISSUE_REASON" "$(read_state_value "$STATE_FILE" "last_attempt_epoch")" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
        log_message "$LOG_FILE" "Auto-restore disabled for $DEVICE_NAME"
        continue
    fi

    DEVICE_BACKUP_PATH="$(find_latest_backup_for_device "$DEVICE_NAME")"
    if [ -z "$DEVICE_BACKUP_PATH" ]; then
        write_state_file "$STATE_FILE" "no_backup_available" "$(read_state_value "$STATE_FILE" "last_attempt_epoch")" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
        log_message "$LOG_FILE" "WARNING: Auto-restore skipped for $DEVICE_NAME because no backup archive contains this device."
        continue
    fi

    LAST_CHANGE_EPOCH="$(latest_mtime_epoch "$TARGET_DIR" || true)"
    if [ -n "$LAST_CHANGE_EPOCH" ]; then
        AGE_SECONDS=$(( NOW_EPOCH - LAST_CHANGE_EPOCH ))
        if [ "$AGE_SECONDS" -lt "$RECENT_CHANGE_GRACE_SECONDS" ]; then
            write_state_file "$STATE_FILE" "recent_change_grace" "$(read_state_value "$STATE_FILE" "last_attempt_epoch")" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
            log_message "$LOG_FILE" "WARNING: Auto-restore skipped for $DEVICE_NAME because files changed ${AGE_SECONDS}s ago (grace ${RECENT_CHANGE_GRACE_SECONDS}s)."
            continue
        fi
    fi

    LAST_ATTEMPT_EPOCH="$(read_state_value "$STATE_FILE" "last_attempt_epoch")"
    if [ -n "$LAST_ATTEMPT_EPOCH" ]; then
        SINCE_LAST_ATTEMPT=$(( NOW_EPOCH - LAST_ATTEMPT_EPOCH ))
        if [ "$SINCE_LAST_ATTEMPT" -lt "$RESTORE_COOLDOWN_SECONDS" ]; then
            write_state_file "$STATE_FILE" "restore_cooldown" "$LAST_ATTEMPT_EPOCH" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
            log_message "$LOG_FILE" "WARNING: Auto-restore skipped for $DEVICE_NAME because cooldown is still active (${SINCE_LAST_ATTEMPT}s/${RESTORE_COOLDOWN_SECONDS}s)."
            continue
        fi
    fi

    write_state_file "$STATE_FILE" "restore_started" "$NOW_EPOCH" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
    log_message "$LOG_FILE" "Attempting auto-restore for $DEVICE_NAME using $DEVICE_BACKUP_PATH"

    if "$RESTORE_SCRIPT" --device "$DEVICE_NAME" --backup "$DEVICE_BACKUP_PATH"; then
        if device_dir_has_state "$TARGET_DIR"; then
            write_state_file "$STATE_FILE" "restore_succeeded" "$NOW_EPOCH" "$NOW_EPOCH"
            log_message "$LOG_FILE" "Auto-restore succeeded for $DEVICE_NAME"
        else
            write_state_file "$STATE_FILE" "restore_incomplete" "$NOW_EPOCH" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
            log_message "$LOG_FILE" "ERROR: Auto-restore finished but session still looks incomplete for $DEVICE_NAME"
        fi
    else
        write_state_file "$STATE_FILE" "restore_failed" "$NOW_EPOCH" "$(read_state_value "$STATE_FILE" "last_success_epoch")"
        log_message "$LOG_FILE" "ERROR: Auto-restore failed for $DEVICE_NAME"
    fi
done

if [ "$HAS_ISSUES" -eq 1 ]; then
    log_message "$LOG_FILE" "ALERT: One or more device sessions need attention."
fi

log_message "$LOG_FILE" "Monitoring finished"
