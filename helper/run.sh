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
        BASHIO_VERSION="$HAOS_CONVERTER_BASHIO_VERSION"
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

# ENV aus options.json exportieren
if [ -f /data/options.json ]; then
    echo "-------------------------------------------------------"
    echo " Loading Add-on configuration options..."
    echo "-------------------------------------------------------"
    
    if command -v bashio >/dev/null 2>&1; then
        echo "Using bashio to export options..."
        # bashio hat keine direkte Funktion um alle Optionen als ENV zu exportieren,
        # aber wir können die Keys loopen oder bashio jq verwenden.
        # Am einfachsten: bashio jq nutzen um die Keys zu bekommen.
        KEYS=$(bashio::config.keys)
        for key in $KEYS; do
            value=$(bashio::config "$key")
            export "$key"="$value"
            echo "export $key=\"$value\""
        done
    elif command -v jq >/dev/null 2>&1; then
        echo "Using jq to export options..."
        # Extrahiere Schlüssel und Werte mit jq
        EXPORT_FILE=$(mktemp)
        jq -r 'to_entries[] | "export \(.key)=\"\(.value)\""' /data/options.json > "$EXPORT_FILE"
        . "$EXPORT_FILE"
        cat "$EXPORT_FILE"
        rm -f "$EXPORT_FILE"
    else
        echo "Using fallback sed parser to export options..."
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
        
        # Zur Info ausgeben
        cat "$EXPORT_FILE"
        
        rm -f "$EXPORT_FILE"
    fi
fi

# Benutzerdefinierte env_vars laden (falls vorhanden)
if [ -f /data/options.json ] && command -v gomplate >/dev/null 2>&1; then
    # Wir prüfen ob "env_vars" in der options.json existiert (einfache Prüfung via grep)
    if grep -q "\"env_vars\"" /data/options.json; then
        echo "-------------------------------------------------------"
        echo " Extracting user defined env_vars using gomplate..."
        echo "-------------------------------------------------------"
        # Gomplate extrahiert die Variablen in einem Format wie: KEY1=VAL1 KEY2=VAL2
        # Wir nutzen ein einfaches Template um die Liste zu generieren
        USER_ENVS=$(gomplate -d options=/data/options.json -i '{{ range (ds "options").env_vars }}{{ . }} {{ end }}')
        
        if [ -n "$USER_ENVS" ]; then
            echo "Loading user variables: $USER_ENVS"
            for env_pair in $USER_ENVS; do
                # Wir splitten KEY=VAL und exportieren
                key=$(echo "$env_pair" | cut -d'=' -f1)
                value=$(echo "$env_pair" | cut -d'=' -f2-)
                if [ -n "$key" ]; then
                    export "$key"="$value"
                    echo "export $key=\"$value\""
                fi
            done
        fi
    fi
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
