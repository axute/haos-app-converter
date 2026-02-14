#!/usr/bin/env bash

DEBUG=0
IMAGE=""

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --debug) DEBUG=1; shift ;;
        *) IMAGE="$1"; shift ;;
    esac
done

if [ -z "$IMAGE" ]; then
    echo "Usage: $0 [--debug] <image>"
    exit 1
fi

debug() {
    if [ "$DEBUG" -eq 1 ]; then
        echo "[DEBUG] $1" >&2
    fi
}

detect_pkg() {
    local image="$1"
    local CRANE_BIN="${CRANE:-crane}"

    debug "Checking image: $image"
    debug "Using crane: $CRANE_BIN"

    if [ "$DEBUG" -eq 1 ]; then
        if command -v jq >/dev/null 2>&1; then
            debug "jq found: $(command -v jq)"
        else
            debug "WARNING: jq not found. History check will fail."
        fi
        if command -v "$CRANE_BIN" >/dev/null 2>&1; then
            debug "crane found: $(command -v "$CRANE_BIN")"
        else
            debug "ERROR: crane not found."
        fi
        if command -v tar >/dev/null 2>&1; then
            debug "tar found: $(command -v tar)"
        else
            debug "WARNING: tar not found. File list check will fail."
        fi
    fi

    # 1. Stufe: History durchsuchen (Schnell)
    local history_raw
    history_raw=$("$CRANE_BIN" config "$image" 2>/dev/null)
    debug "Raw history config obtained (length: ${#history_raw})"

    local history
    history=$(echo "$history_raw" | jq -r '.history[].created_by // empty' 2>/dev/null)

    if [ -n "$history" ]; then
        debug "History found:"
        debug "$history"
        debug "Checking for package manager patterns..."
        if echo "$history" | grep -qiE "apk|alpine"; then debug "Found 'apk' related pattern in history"; echo "apk"; return; fi
        if echo "$history" | grep -qiE "debian|ubuntu|apt"; then debug "Found 'apt' related pattern in history"; echo "apt"; return; fi
        if echo "$history" | grep -qiE "yum|dnf|centos|fedora"; then debug "Found 'yum' related pattern in history"; echo "yum"; return; fi
        if echo "$history" | grep -qi "zypper"; then debug "Found 'zypper' in history"; echo "zypper"; return; fi
        if echo "$history" | grep -qi "pacman"; then debug "Found 'pacman' in history"; echo "pacman"; return; fi
        if echo "$history" | grep -qi "busybox"; then debug "Found 'busybox' in history"; echo "busybox"; return; fi
        debug "No known package manager found in history."
    else
        debug "No history found or history empty."
    fi

    # 2. Stufe: Dateisystem durchsuchen (Gründlich, falls History nicht eindeutig)
    # Wir nutzen 'crane export' um die Dateiliste zu streamen, ohne das Image zu laden
    local files
    # Wir suchen nach markanten Dateien/Verzeichnissen
    debug "Exporting file list from image..."
    files=$("$CRANE_BIN" export "$image" - 2>/dev/null | tar -tf - 2>/dev/null)

    if [ -n "$files" ]; then
        debug "File list obtained, checking for marker files..."
        if echo "$files" | grep -E "etc/apk/|lib/apk/|sbin/apk" >/dev/null 2>&1; then debug "Found apk marker files"; echo "apk"; return; fi
        if echo "$files" | grep -E "usr/bin/apt|var/lib/dpkg/" >/dev/null 2>&1; then debug "Found apt marker files"; echo "apt"; return; fi
        if echo "$files" | grep -E "usr/bin/yum|usr/bin/dnf|etc/yum.repos.d/" >/dev/null 2>&1; then debug "Found yum marker files"; echo "yum"; return; fi
        if echo "$files" | grep -E "usr/bin/zypper|etc/zypp/" >/dev/null 2>&1; then debug "Found zypper marker files"; echo "zypper"; return; fi
        if echo "$files" | grep -E "usr/bin/pacman|etc/pacman.conf" >/dev/null 2>&1; then debug "Found pacman marker files"; echo "pacman"; return; fi
        if echo "$files" | grep "bin/busybox" >/dev/null 2>&1; then debug "Found busybox marker file"; echo "busybox"; return; fi
        debug "No known marker files found in file list."
    else
        debug "Could not obtain file list or image is empty."
    fi

    # 3. Spezialfall: Scratch / Distroless (nur wenn History vorhanden aber leer/minimal)
    if [ -n "$history" ]; then
        if echo "$history" | grep -qiE "scratch|distroless"; then
            debug "History mentions scratch or distroless."
            echo "none"
            return
        fi
    fi

    # Wenn wir gar nichts gefunden haben, aber files/history da waren, ist es vielleicht wirklich leer oder unbekannt
    if [ -z "$history" ] && [ -z "$files" ]; then
        debug "Both history and file list are empty. Result: unknown"
        echo "unknown"
    else
        debug "Finished checks, no package manager identified. Result: unknown"
        echo "unknown"
    fi
}

detect_pkg "$IMAGE"
