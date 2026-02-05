#!/bin/sh
set -e

# Auto-install bash and jq if PM is known
if [ -n "$HAOS_CONVERTER_PM" ]; then
    echo "Detected package manager: $HAOS_CONVERTER_PM. Attempting to install bash and jq..."
    case "$HAOS_CONVERTER_PM" in
        apk)
            apk add --no-cache bash jq curl ca-certificates || echo "Failed to install tools via apk"
            ;;
        apt|apt-get)
            export DEBIAN_FRONTEND=noninteractive
            apt-get update || echo "apt-get update failed"
            apt-get install -y bash jq curl ca-certificates || echo "Failed to install tools via apt-get"
            ;;
        yum|dnf)
            $HAOS_CONVERTER_PM install -y bash jq curl ca-certificates || echo "Failed to install tools via $HAOS_CONVERTER_PM"
            ;;
        microdnf)
            microdnf install -y bash jq curl ca-certificates || echo "Failed to install tools via microdnf"
            ;;
        zypper)
            zypper install -y bash jq curl ca-certificates || echo "Failed to install tools via zypper"
            ;;
        pacman)
            pacman -Sy --noconfirm bash jq curl ca-certificates || echo "Failed to install tools via pacman"
            ;;
        *)
            echo "Auto-install not supported for $HAOS_CONVERTER_PM"
            ;;
    esac
fi

# Install bashio if bash, jq and curl are available but bashio is missing
if command -v bash >/dev/null 2>&1 && command -v jq >/dev/null 2>&1 && command -v curl >/dev/null 2>&1; then
    if ! command -v bashio >/dev/null 2>&1; then
        BASHIO_VERSION=$(curl -s https://api.github.com/repos/hassio-addons/bashio/releases/latest | grep '"tag_name":' | sed -E 's/.*"v?([^"]+)".*/\1/')
        if [ -z "$BASHIO_VERSION" ]; then
            BASHIO_VERSION="0.16.3"
        fi
        echo "bash, jq and curl found, but bashio is missing. Attempting to install bashio v${BASHIO_VERSION}..."
        mkdir -p /tmp/bashio
        curl -L -f -s "https://github.com/hassio-addons/bashio/archive/v${BASHIO_VERSION}.tar.gz" | tar -xzf - --strip 1 -C /tmp/bashio || echo "Failed to download bashio"
        if [ -d /tmp/bashio/lib ]; then
            mkdir -p /usr/lib/bashio
            cp -r /tmp/bashio/lib/* /usr/lib/bashio/
            ln -s /usr/lib/bashio/bashio /usr/bin/bashio
            chmod +x /usr/bin/bashio
            echo "bashio v${BASHIO_VERSION} installed successfully"
        fi
        rm -rf /tmp/bashio
    fi
fi

# ENV aus options.json exportieren (einfacher Parser ohne jq)
if [ -f /data/options.json ]; then
    # Wir extrahieren die Keys und Values mit sed. 
    # Dies funktioniert zuverlässig für flache JSON-Objekte, wie sie in HA Options üblich sind.
    # Wir suchen nach "key": "value" oder "key": 123
    echo "/data/options.json found"

    # Temporäre Datei für die Exporte
    EXPORT_FILE=$(mktemp)
    
    # Extrahiere Schlüssel und Werte
    # 1. Suche Zeilen mit ":"
    # 2. Entferne führende Leerzeichen
    # 3. Entferne abschließendes Komma und Leerzeichen
    # 4. Ersetze "key": "value" durch export key="value" (unterstützt Strings, Zahlen, Booleans)
    grep ":" /data/options.json | sed -E \
        -e 's/^[[:space:]]*//' \
        -e 's/[[:space:]]*,?$//' \
        -e 's/"([^"]*)":[[:space:]]*"([^"]*)"/export \1="\2"/' \
        -e 's/"([^"]*)":[[:space:]]*([0-9.]+)/export \1="\2"/' \
        -e 's/"([^"]*)":[[:space:]]*(true|false)/export \1="\2"/' \
        | grep "^export " > "$EXPORT_FILE" || true
    
    # Source die Exporte
    . "$EXPORT_FILE"
    
    # Zur Info ausgeben (maskiert Passwort-ähnliche Keys evtl?)
    while read -r line; do
        echo "$line"
    done < "$EXPORT_FILE"
    
    rm -f "$EXPORT_FILE"
fi

# Source start.sh if exists
if [ -f /start.sh ]; then
    echo "Sourcing /start.sh..."
    . /start.sh
fi

# Originalwerte laden
orig_entrypoint=$(cat /run/original_entrypoint 2>/dev/null || echo "")
orig_cmd=$(cat /run/original_cmd 2>/dev/null || echo "")

# Wenn HA ein CMD gesetzt hat, wird es als Argument übergeben
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Wenn das Image ein ENTRYPOINT hatte
if [ -n "$orig_entrypoint" ] && [ "$orig_entrypoint" != "null" ]; then
    # Wichtig: Variable expansion für orig_entrypoint und orig_cmd
    # Wir nutzen eval, damit evtl. enthaltene Leerzeichen in den Originalbefehlen korrekt interpretiert werden
    # Aber Vorsicht bei eval. Da wir die Werte selbst geschrieben haben (aus crane), sollte es okay sein.
    exec $orig_entrypoint $orig_cmd
fi

# Wenn nur CMD existiert
exec $orig_cmd
