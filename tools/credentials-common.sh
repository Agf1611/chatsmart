#!/bin/bash

set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_ENV_FILE="${APP_ENV_FILE:-$PROJECT_ROOT/.env}"
DEFAULT_BACKUP_DIR="$PROJECT_ROOT/backup/credentials"
DEFAULT_ACTIVE_DIR="$PROJECT_ROOT/storage/app/wa-sessions"
DEFAULT_SNAPSHOT_DIR="$PROJECT_ROOT/storage/app/wa-sessions-snapshots"
DEFAULT_MONITOR_STATE_DIR="$PROJECT_ROOT/storage/app/wa-monitor"
DEFAULT_LOG_DIR="$PROJECT_ROOT/storage/logs"
LOCK_ROOT="${TMPDIR:-/tmp}"

looks_like_windows_path() {
    case "${1:-}" in
        [A-Za-z]:\\*|[A-Za-z]:/*|\\\\*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

read_env_value() {
    local key="$1"
    local default_value="${2:-}"

    if [ -n "${!key:-}" ]; then
        printf '%s\n' "${!key}"
        return 0
    fi

    if [ ! -f "$APP_ENV_FILE" ]; then
        printf '%s\n' "$default_value"
        return 0
    fi

    local value
    value="$(grep -E "^${key}=" "$APP_ENV_FILE" | tail -n 1 | cut -d '=' -f 2-)"
    if [ -z "$value" ]; then
        printf '%s\n' "$default_value"
        return 0
    fi

    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    printf '%s\n' "$value"
}

get_credentials_dir() {
    local configured_path
    local server_type

    configured_path="$(read_env_value "WA_CREDENTIALS_PATH" "")"
    server_type="$(printf '%s' "$(read_env_value "TYPE_SERVER" "")" | tr '[:upper:]' '[:lower:]')"

    if [ -z "$configured_path" ] || { [ "$server_type" = "hosting" ] && looks_like_windows_path "$configured_path"; }; then
        configured_path="$DEFAULT_ACTIVE_DIR"
    fi

    printf '%s\n' "$configured_path"
}

get_backup_dir() {
    printf '%s\n' "${BACKUP_DIR:-$DEFAULT_BACKUP_DIR}"
}

get_snapshot_dir() {
    printf '%s\n' "${SNAPSHOT_DIR:-$DEFAULT_SNAPSHOT_DIR}"
}

get_monitor_state_dir() {
    printf '%s\n' "${MONITOR_STATE_DIR:-$DEFAULT_MONITOR_STATE_DIR}"
}

get_log_dir() {
    printf '%s\n' "${LOG_DIR:-$DEFAULT_LOG_DIR}"
}

ensure_directory() {
    mkdir -p "$1"
}

timestamp_now() {
    date +"%Y%m%d_%H%M%S"
}

log_message() {
    local log_file="$1"
    shift
    ensure_directory "$(dirname "$log_file")"
    printf '[%s] %s\n' "$(date +"%Y-%m-%d %H:%M:%S")" "$*" >> "$log_file"
}

acquire_lock() {
    local name="$1"
    local lock_dir="$LOCK_ROOT/${name}.lock"

    if mkdir "$lock_dir" 2>/dev/null; then
        LOCK_DIR="$lock_dir"
        trap release_lock EXIT INT TERM
        return 0
    fi

    return 1
}

release_lock() {
    if [ -n "${LOCK_DIR:-}" ] && [ -d "$LOCK_DIR" ]; then
        rmdir "$LOCK_DIR" 2>/dev/null || true
    fi
}

is_reserved_session_dir() {
    case "$1" in
        credentials|wa-sessions|wa-sessions-backup|wa-sessions-snapshots|snapshots|tmp|temp)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

is_valid_device_name() {
    local name="$1"

    if [ -z "$name" ]; then
        return 1
    fi

    if is_reserved_session_dir "$name"; then
        return 1
    fi

    printf '%s' "$name" | grep -Eq '^[0-9A-Za-z._-]+$'
}

device_dir_has_state() {
    local dir="$1"

    [ -d "$dir" ] || return 1
    [ -f "$dir/creds.json" ] || return 1

    find "$dir" -maxdepth 1 -type f \
        \( -name 'session-*' -o -name 'app-state-sync-key-*' -o -name 'sender-key-*' -o -name 'pre-key-*' -o -name 'sender-key-memory-*' \) \
        -print -quit | grep -q .
}

list_candidate_device_dirs() {
    local root="$1"

    [ -d "$root" ] || return 0

    find "$root" -mindepth 1 -maxdepth 1 -type d | while IFS= read -r dir; do
        local name
        name="$(basename "$dir")"
        if is_valid_device_name "$name"; then
            printf '%s\n' "$dir"
        fi
    done
}

list_device_dirs_with_state() {
    local root="$1"

    list_candidate_device_dirs "$root" | while IFS= read -r dir; do
        if device_dir_has_state "$dir"; then
            printf '%s\n' "$dir"
        fi
    done
}

mysql_command_available() {
    command -v mysql >/dev/null 2>&1
}

get_expected_devices_from_database() {
    local db_host db_port db_name db_user db_password

    mysql_command_available || return 0

    db_host="$(read_env_value "DB_HOST" "")"
    db_port="$(read_env_value "DB_PORT" "3306")"
    db_name="$(read_env_value "DB_DATABASE" "")"
    db_user="$(read_env_value "DB_USERNAME" "")"
    db_password="$(read_env_value "DB_PASSWORD" "")"

    if [ -z "$db_host" ] || [ -z "$db_name" ] || [ -z "$db_user" ]; then
        return 0
    fi

    mysql \
        --host="$db_host" \
        --port="$db_port" \
        --user="$db_user" \
        --password="$db_password" \
        --database="$db_name" \
        --batch \
        --skip-column-names \
        --execute="SELECT body FROM devices WHERE body IS NOT NULL AND body <> '';" 2>/dev/null | while IFS= read -r device; do
            if is_valid_device_name "$device"; then
                printf '%s\n' "$device"
            fi
        done
}

find_latest_backup_archive() {
    local backup_dir

    backup_dir="$(get_backup_dir)"
    [ -d "$backup_dir" ] || return 0

    find "$backup_dir" -maxdepth 1 -type f -name 'credentials_*.tar.gz' | sort | tail -n 1
}

backup_archive_contains_device() {
    local archive_path="$1"
    local device_name="$2"

    [ -f "$archive_path" ] || return 1

    tar -tzf "$archive_path" 2>/dev/null | grep -Eq "(^|/)(devices/)?${device_name}/creds\\.json$"
}

find_latest_backup_for_device() {
    local device_name="$1"
    local backup_dir

    backup_dir="$(get_backup_dir)"
    [ -d "$backup_dir" ] || return 0

    find "$backup_dir" -maxdepth 1 -type f -name 'credentials_*.tar.gz' | sort -r | while IFS= read -r archive_path; do
        if backup_archive_contains_device "$archive_path" "$device_name"; then
            printf '%s\n' "$archive_path"
            break
        fi
    done
}

latest_mtime_epoch() {
    local target_path="$1"

    [ -e "$target_path" ] || return 1

    find "$target_path" -printf '%T@\n' 2>/dev/null | sort -n | tail -n 1 | cut -d '.' -f 1
}

write_manifest() {
    local manifest_file="$1"
    local source_dir="$2"
    shift 2

    {
        printf 'created_at=%s\n' "$(date -Iseconds)"
        printf 'source_dir=%s\n' "$source_dir"
        printf 'project_root=%s\n' "$PROJECT_ROOT"
        printf 'devices=\n'
        printf '%s\n' "$@"
    } > "$manifest_file"
}

sync_directory_contents() {
    local source_dir="$1"
    local target_dir="$2"

    rm -rf "$target_dir"
    mkdir -p "$target_dir"
    cp -a "$source_dir"/. "$target_dir"/

    if command -v chown >/dev/null 2>&1 && [ -d "$(dirname "$target_dir")" ]; then
        chown -R --reference="$(dirname "$target_dir")" "$target_dir" 2>/dev/null || true
    fi
}
