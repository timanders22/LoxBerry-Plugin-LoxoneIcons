#!/bin/sh
# Loxone Icons - postupgrade (laeuft als Benutzer loxberry)

ARGV1=$1
ARGV3=$3
ARGV5=$5

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-loxoneicons}"
SICHER="$BASE/data/plugins/${PDIR}.upgrade_sicherung"

mkdir -p "$BASE/data/plugins/$PDIR" 2>/dev/null

# Wer von 2.0.0 oder frueher kommt, hat die Sicherung noch in der Ramdisk.
if [ ! -d "$SICHER/loxone_icons" ] && [ -d "/tmp/${ARGV1}_upgrade/data/loxone_icons" ]; then
    mkdir -p "$SICHER" 2>/dev/null
    cp -a "/tmp/${ARGV1}_upgrade/data/loxone_icons" "$SICHER/loxone_icons" 2>/dev/null
    echo "<INFO> Sicherung am alten Ort (/tmp) gefunden und uebernommen."
fi

if [ -d "$SICHER/loxone_icons" ]; then
    rm -rf "$BASE/data/plugins/$PDIR/loxone_icons" 2>/dev/null
    if mv "$SICHER/loxone_icons" "$BASE/data/plugins/$PDIR/loxone_icons" 2>/dev/null; then
        echo "<OK> Heruntergeladene Icons zurueckgestellt."
    else
        cp -a "$SICHER/loxone_icons" "$BASE/data/plugins/$PDIR/loxone_icons" 2>/dev/null \
            && echo "<OK> Heruntergeladene Icons zurueckgestellt (kopiert)."
    fi
fi

rm -rf "$SICHER" 2>/dev/null
rm -rf "/tmp/${ARGV1}_upgrade" 2>/dev/null

# Ergaenzen, was nach dem Zurueckstellen fehlt. Ist alles da, ist das Skript
# in einer Sekunde fertig - es laedt nur, was noch nicht vorhanden ist.
echo "<INFO> Pruefe den Iconsatz auf Vollstaendigkeit"
if ! "$BASE/bin/plugins/$PDIR/download_icons.sh"; then
    echo "<WARNING> Die Pruefung des Iconsatzes lief nicht sauber durch. Sie laesst"
    echo "<WARNING> sich jederzeit ueber die Schaltflaeche in der Plugin-Oberflaeche"
    echo "<WARNING> erneut anstossen."
fi

exit 0
