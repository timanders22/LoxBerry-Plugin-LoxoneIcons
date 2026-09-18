#!/bin/sh
# Loxone Icons - preupgrade (laeuft als Benutzer loxberry)

ARGV3=$3   # Installationsordner des Plugins
ARGV5=$5   # Wurzelverzeichnis des LoxBerry

BASE="${ARGV5:-$LBHOMEDIR}"
PDIR="${ARGV3:-loxoneicons}"

# Der heruntergeladene Iconsatz wird VERSCHOBEN, nicht nach /tmp kopiert.
#
# Bis 2.0.0 stand hier
#     cp -p -r $ARGV5/data/plugins/$ARGV3/loxone_icons /tmp/$ARGV1\_upgrade/data
# Das ist gleich dreifach unguenstig:
#
#   1. /tmp ist auf dem LoxBerry eine RAMDISK. Der Iconsatz von Loxone
#      umfasst mehrere tausend Dateien; ihn in den Arbeitsspeicher zu
#      kopieren kann auf einem kleinen Raspberry Pi den Speicher fuellen.
#   2. Bei einem Neustart mitten im Update waere er weg - und das erneute
#      Herunterladen dauert Minuten und belastet die Server von Loxone.
#   3. $ARGV1\_upgrade verlaesst sich auf den Rueckstrich als Ende des
#      Variablennamens. ${ARGV1}_upgrade waere eindeutig.
#
# mv innerhalb desselben Dateisystems ist ein Umbenennen: augenblicklich,
# ohne zusaetzlichen Platzbedarf, und es liegt auf der Karte.
SICHER="$BASE/data/plugins/${PDIR}.upgrade_sicherung"
LAUFEND="$BASE/data/plugins/$PDIR/loxone_icons"
# Die Nebenablagen tragen die Nummer DIESES Laufs. Ein Rest eines frueheren,
# abgebrochenen Laufs kann die einzige Abschrift sein; er wird hier nie
# geloescht, sondern bleibt liegen, bis uninstall/uninstall ihn wegraeumt.
NEU="$SICHER.neu.$$"
ALT="$SICHER.alt.$$"

# Wie viele vollstaendige SVG traegt ein Satz? Dieselbe Regel wie
# vorhanden() in bin/download_icons.sh: nicht leer und mit </svg>.
li_satz_zahl() {
    find "$1/svg/filled" "$1/svg/outlined" -maxdepth 1 -type f -name '*.svg' -size +0 \
        -exec grep -l '</svg>' {} + 2>/dev/null | wc -l
}

# Bis 2.0.8 stand hier als Erstes "rm -rf $SICHER" - die vorhandene Sicherung
# fiel, bevor feststand, ob es etwas Neues zu sichern gab. Nach
# purge_installation ist sie aber die EINZIGE Abschrift des Satzes: bricht
# das Upgrade danach ab und wird erneut angestossen, loeschte dieser zweite
# Lauf sie. In WSL gemessen (18.09.2026, Pruefung-LoxoneIcons-2.0.8, Faelle
# D1 und D2): 0 von 5 Merkdateien uebrig - ob der Datenordner fehlte (D1)
# oder der Installer schon loxone_icons/.gitkeep aus dem Archiv kopiert hatte
# (D2, plugininstall.pl:1013).
#
# Deshalb, in dieser Reihenfolge:
#   1. Traegt der laufende Satz nichts, bleibt eine liegende Sicherung, wie
#      sie ist.
#   2. Sonst entsteht die neue Sicherung NEBEN der alten (.neu), wird
#      gezaehlt, und erst dann tauscht sie per Umbenennen den Platz mit der
#      alten (Bauart GardenaSmartSystem 1.2.10, preupgrade.sh).
#   3. Scheitert etwas, bleibt die alte Sicherung unangetastet, und es kommt
#      eine Warnung statt eines stillen Endes.

N_LAUFEND=0
[ -d "$LAUFEND" ] && N_LAUFEND=$(li_satz_zahl "$LAUFEND")

# --- 1. Nichts zu sichern: die liegende Sicherung bleibt ---
if [ "$N_LAUFEND" -eq 0 ]; then
    if [ -d "$SICHER/loxone_icons" ]; then
        echo "<WARNING> Eine Sicherung aus einem nicht abgeschlossenen Update liegt noch"
        echo "<WARNING> ($(li_satz_zahl "$SICHER/loxone_icons") Symbole). Sie bleibt unangetastet und wird"
        echo "<WARNING> nach dem Update zurueckgestellt."
    else
        echo "<INFO> Noch kein heruntergeladener Iconsatz vorhanden."
    fi
    exit 0
fi

# --- 2. Neue Sicherung neben der alten, gezaehlt, dann getauscht ---
mkdir -p "$NEU" 2>/dev/null
WIE=""
if mv "$LAUFEND" "$NEU/loxone_icons" 2>/dev/null; then
    WIE="verschoben"
elif cp -a "$LAUFEND" "$NEU/loxone_icons" 2>/dev/null; then
    # Anderes Dateisystem: dann bleibt nur das Kopieren.
    WIE="kopiert"
fi
N_NEU=0
[ -d "$NEU/loxone_icons" ] && N_NEU=$(li_satz_zahl "$NEU/loxone_icons")

# Gibt den Satz an seinen Ort zurueck, wenn er verschoben war, und raeumt
# die Nebenablage nur dann weg, wenn sie keine einzige Abschrift mehr ist.
li_neu_zurueck() {
    if [ "$WIE" = "verschoben" ]; then
        mv "$NEU/loxone_icons" "$LAUFEND" 2>/dev/null || return 1
    fi
    rm -rf "$NEU" 2>/dev/null
    return 0
}

if [ "$N_NEU" -ne "$N_LAUFEND" ]; then
    li_neu_zurueck
    echo "<WARNING> Der Iconsatz liess sich nicht sichern ($N_NEU von $N_LAUFEND Symbolen)."
    if [ -d "$SICHER/loxone_icons" ]; then
        echo "<WARNING> Die bisherige Sicherung unter $SICHER bleibt unangetastet."
    fi
    echo "<WARNING> Nach dem Update fehlende Symbole laedt postupgrade.sh nach."
    exit 0
fi

if [ -d "$SICHER" ] && ! mv "$SICHER" "$ALT" 2>/dev/null; then
    li_neu_zurueck || echo "<WARNING> Der Satz liegt unter $NEU."
    echo "<WARNING> Die bisherige Sicherung liess sich nicht beiseitelegen; sie bleibt unangetastet."
    exit 0
fi
if mv "$NEU" "$SICHER" 2>/dev/null; then
    rm -rf "$ALT" 2>/dev/null
    echo "<OK> Heruntergeladene Icons beiseitegelegt ($N_NEU Symbole, $WIE)."
else
    [ -d "$ALT" ] && mv "$ALT" "$SICHER" 2>/dev/null
    li_neu_zurueck || echo "<WARNING> Der Satz liegt unter $NEU."
    echo "<WARNING> Die neue Sicherung liess sich nicht an ihren Platz bringen."
fi

exit 0
