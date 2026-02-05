#!/bin/sh
set -e

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
