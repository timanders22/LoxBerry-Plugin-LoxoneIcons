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

rm -rf "$SICHER" 2>/dev/null
if [ -d "$BASE/data/plugins/$PDIR/loxone_icons" ]; then
    mkdir -p "$SICHER" 2>/dev/null
    if mv "$BASE/data/plugins/$PDIR/loxone_icons" "$SICHER/loxone_icons" 2>/dev/null; then
        echo "<OK> Heruntergeladene Icons beiseitegelegt (verschoben, nicht kopiert)."
    else
        # Anderes Dateisystem: dann bleibt nur das Kopieren.
        cp -a "$BASE/data/plugins/$PDIR/loxone_icons" "$SICHER/loxone_icons" 2>/dev/null \
            && echo "<OK> Heruntergeladene Icons gesichert (kopiert)."
    fi
else
    echo "<INFO> Noch kein heruntergeladener Iconsatz vorhanden."
fi

exit 0
