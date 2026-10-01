#!/bin/bash

# LoxoneIcons NG - preinstall
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# Neu im Verbesserungsbau vom 01.10.2026 (X-1, Entscheidung 1 vom 29.09.2026;
# Muster: Abfahrts-Assistent 1.6.16). Der Installer ruft dieses Skript bei
# JEDEM Einbau auf, nach dem Aufraeumen der alten Fassung und VOR dem Kopieren
# von Konfiguration und Oberflaeche (sbin/plugininstall.pl: preupgrade :846,
# purge :874, preinstall :877, HTML :1066 - Geraet/2026-09-05/08_plugininstall.pl).
#
# Eine Aktualisierung erkennt es allein an der Marke
# data/plugins/<ordner>.upgrade_laeuft, die preupgrade.sh als Erstes anlegt
# (kein Altersvergleich). Dann tut es nichts: Zweitschrift und Symbolsicherung
# brauchen postinstall.sh und postupgrade.sh zum Zurueckspielen.
#
# Ohne Marke ist es eine NEUINSTALLATION. Eine liegengebliebene Zweitschrift
# (config/plugins/<ordner>.backup.json, traegt das Aktionstoken) und eine
# liegengebliebene Symbolsicherung (data/plugins/<ordner>.upgrade_sicherung)
# einer frueheren Installation gehen nach <name>.alt, gemeldet mit genau einer
# <WARNING>.
#
# Warum schon hier und nicht erst in postinstall.sh: die Oberflaeche liegt
# vor postinstall.sh an ihrem Platz. Ein Seitenaufruf in diesem Fenster findet
# keine loxoneicons.json, liest die Zweitschrift und stellt das Aktionstoken
# der frueheren Installation zurueck; postinstall.sh legte die Zweitschrift
# danach zwar beiseite, die geheilte loxoneicons.json blieb aber stehen (in WSL
# gemessen, vb_li2_bau_skripte/proben/x1_*). Die Selbstheilung der Oberflaeche
# liest .alt nie; die Deinstallation raeumt es ab.

ARGV3=$3
ARGV5=$5
# Rueckfall, falls sudo die Umgebung ausgeraeumt hat (env_reset).
# Das fuenfte Argument ist das Wurzelverzeichnis und traegt immer.
LBHOMEDIR="${LBHOMEDIR:-$5}"
PFOLDER="${ARGV3:-loxoneicons}"
BASE="${ARGV5:-$LBHOMEDIR}"

# Wurzelsuche wie in bin/download_icons.sh und der Oberflaeche: ohne
# config/plugins, data/plugins UND config/system/general.json wird nichts
# angefasst (Regeln/06).
if [ -z "$BASE" ] || [ ! -d "$BASE/config/plugins" ] || [ ! -d "$BASE/data/plugins" ] \
   || [ ! -f "$BASE/config/system/general.json" ]; then
    echo "<WARNING> Kein LoxBerry-Wurzelverzeichnis erkannt ('$BASE') - nichts beiseitegelegt."
    exit 0
fi
# Der Ordnername darf keinen Pfadtrenner tragen, sonst griffe mv/rm daneben.
case "$PFOLDER" in
    ''|*/*|*..*) echo "<WARNING> Unzulaessiger Ordnername '$PFOLDER' - nichts beiseitegelegt."; exit 0 ;;
esac

MARKE="$BASE/data/plugins/$PFOLDER.upgrade_laeuft"
if [ -f "$MARKE" ]; then
    # Aktualisierung: nichts zu tun, postinstall.sh/postupgrade.sh spielen zurueck.
    exit 0
fi

ZWEIT="$BASE/config/plugins/$PFOLDER.backup.json"
SICHER="$BASE/data/plugins/$PFOLDER.upgrade_sicherung"
BEISEITE=""
FEST=""
for ZIEL in "$ZWEIT" "$SICHER"; do
    if [ -e "$ZIEL" ] || [ -L "$ZIEL" ]; then
        rm -rf "${ZIEL:?}.alt" 2>/dev/null
        if mv -f "$ZIEL" "$ZIEL.alt" 2>/dev/null; then
            BEISEITE="$BEISEITE $ZIEL.alt"
        else
            FEST="$FEST $ZIEL"
        fi
    fi
done
[ -f "$ZWEIT.alt" ] && [ ! -L "$ZWEIT.alt" ] && chmod 600 "$ZWEIT.alt" 2>/dev/null

if [ -n "$BEISEITE" ] || [ -n "$FEST" ]; then
    LI_TEXT="<WARNING> Neuinstallation: Einstellungen und Symbole einer frueheren Installation werden NICHT eingespielt."
    [ -n "$BEISEITE" ] && LI_TEXT="$LI_TEXT Beiseitegelegt:$BEISEITE (die Deinstallation raeumt sie ab)."
    [ -n "$FEST" ] && LI_TEXT="$LI_TEXT Nicht zu verschieben, bitte von Hand entfernen:$FEST"
    echo "$LI_TEXT"
fi
exit 0
