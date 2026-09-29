#!/bin/sh
# Loxone Icons - postupgrade (laeuft als Benutzer loxberry)

ARGV1=$1
ARGV3=$3
ARGV5=$5

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-loxoneicons}"
SICHER="$BASE/data/plugins/${PDIR}.upgrade_sicherung"
LAUFEND="$BASE/data/plugins/$PDIR/loxone_icons"

# Dieselbe Zaehlung wie in preupgrade.sh (und vorhanden() in
# bin/download_icons.sh): vollstaendige SVG, nicht leer und mit </svg>.
li_satz_zahl() {
    find "$1/svg/filled" "$1/svg/outlined" -maxdepth 1 -type f -name '*.svg' -size +0 \
        -exec grep -l '</svg>' {} + 2>/dev/null | wc -l
}

mkdir -p "$BASE/data/plugins/$PDIR" 2>/dev/null

# Wer von 2.0.0 oder frueher kommt, hat die Sicherung noch in der Ramdisk.
if [ ! -d "$SICHER/loxone_icons" ] && [ -d "/tmp/${ARGV1}_upgrade/data/loxone_icons" ]; then
    mkdir -p "$SICHER" 2>/dev/null
    cp -a "/tmp/${ARGV1}_upgrade/data/loxone_icons" "$SICHER/loxone_icons" 2>/dev/null
    echo "<INFO> Sicherung am alten Ort (/tmp) gefunden und uebernommen."
fi

# Die Sicherung faellt nur, wenn das Zurueckstellen nachweislich gelungen ist.
#
# Bis 2.0.8 stand das "rm -rf $SICHER" unbedingt hinter diesem Block - auch
# dann, wenn weder mv noch cp -a gelungen waren. In WSL gemessen (18.09.2026,
# Pruefung-LoxoneIcons-2.0.8, Fall P1: Datenordner nicht beschreibbar):
# Sicherung 0 von 5, der Datenordner leer, und keine einzige Zeile im
# Protokoll sagte, dass etwas fehlte.
ZURUECK=1
if [ -d "$SICHER/loxone_icons" ]; then
    N_SICHER=$(li_satz_zahl "$SICHER/loxone_icons")
    rm -rf "$LAUFEND" 2>/dev/null
    if mv "$SICHER/loxone_icons" "$LAUFEND" 2>/dev/null; then
        WIE=""
    else
        cp -a "$SICHER/loxone_icons" "$LAUFEND" 2>/dev/null
        WIE=" (kopiert)"
    fi
    N_LAUFEND=0
    [ -d "$LAUFEND" ] && N_LAUFEND=$(li_satz_zahl "$LAUFEND")
    if [ "$N_LAUFEND" -ge "$N_SICHER" ]; then
        echo "<OK> Heruntergeladene Icons zurueckgestellt$WIE ($N_LAUFEND Symbole)."
    else
        ZURUECK=0
        echo "<WARNING> Die heruntergeladenen Icons liessen sich nicht zurueckstellen"
        echo "<WARNING> ($N_LAUFEND von $N_SICHER Symbolen). Die Sicherung bleibt unter"
        echo "<WARNING> $SICHER liegen; Platz und Rechte unter data/plugins pruefen."
    fi
fi

if [ "$ZURUECK" -eq 1 ]; then
    rm -rf "$SICHER" 2>/dev/null
fi
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
