#!/usr/bin/env bash

detect_pkg() {
    local image="$1"

    # crane binary variabel machen
    local CRANE_BIN="${CRANE:-crane}"

    # 1. Config auslesen (History)
    local history
    history=$("$CRANE_BIN" config "$image" 2>/dev/null | jq -r '.history[].created_by // empty')

    # 2. Dateisystem auslesen (mit Tag!)
    local files
    files=$("$CRANE_BIN" ls --recursive "$image" 2>/dev/null)

    # --- Alpine / apk ---
    if echo "$history" | grep -qi "apk" \
       || echo "$files" | grep -q "/sbin/apk"; then
        echo "apk"
        return
    fi

    # --- Debian/Ubuntu / apt ---
    if echo "$history" | grep -qiE "debian|ubuntu|apt" \
       || echo "$files" | grep -q "/usr/bin/apt-get"; then
        echo "apt"
        return
    fi

    # --- RHEL/Fedora / yum/dnf ---
    if echo "$history" | grep -qiE "yum|dnf|centos|fedora" \
       || echo "$files" | grep -q "/usr/bin/yum"; then
        echo "yum"
        return
    fi

    # --- SUSE / zypper ---
    if echo "$history" | grep -qi "zypper" \
       || echo "$files" | grep -q "/usr/bin/zypper"; then
        echo "zypper"
        return
    fi

    # --- Arch / pacman ---
    if echo "$history" | grep -qi "pacman" \
       || echo "$files" | grep -q "/usr/bin/pacman"; then
        echo "pacman"
        return
    fi

    # --- BusyBox ---
    if echo "$history" | grep -qi "busybox" \
       || echo "$files" | grep -q "/bin/busybox"; then
        echo "busybox"
        return
    fi

    # --- Scratch / Distroless ---
    if [ -z "$history" ] \
       || echo "$history" | grep -qi "scratch" \
       || echo "$history" | grep -qi "distroless"; then
        echo "none"
        return
    fi

    echo "unknown"
}

detect_pkg $1
