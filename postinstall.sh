#!/bin/bash

# Bashscript which is executed by bash *AFTER* complete installation is done
# (but *BEFORE* postupgrade). Use with caution and remember, that all systems
# may be different! Better to do this in your own Pluginscript if possible.
#
# Exit code must be 0 if executed successfull.
#
# Will be executed as user "loxberry".
#
# We add 5 arguments when executing the script:
# command <TEMPFOLDER> <NAME> <FOLDER> <VERSION> <BASEFOLDER>
#
# For logging, print to STDOUT. You can use the following tags for showing
# different colorized information during plugin installation:
#
# <OK> This was ok!"
# <INFO> This is just for your information."
# <WARNING> This is a warning!"
# <ERROR> This is an error!"
# <FAIL> This is a fail!"

# To use important variables from command line use the following code:
ARGV0=$0 # Zero argument is shell command
ARGV1=$1 # First argument is temp folder during install
ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
ARGV3=$3 # Third argument is Plugin installation folder
ARGV4=$4 # Forth argument is Plugin version
ARGV5=$5 # Fifth argument is Base folder of LoxBerry

pluginname=$3

# ---------------------------------------------------------------------------
# Pfade: Umgebung bevorzugt, Ableitung als Netz
#
# $LBPDATA, $LBPHTMLAUTH und $LBPBIN SIND hier gesetzt. LoxBerry ruft dieses
# Skript mit "sudo -n -u loxberry ..." auf (plugininstall.pl), und zwei Dinge
# sorgen dafuer, dass die Variablen den sudo-Aufruf ueberleben:
#
#   1. /etc/pam.d/sudo enthaelt auf Debian
#          session required pam_env.so readenv=1
#      - sudo liest also /etc/environment ein, wo LoxBerry die Pfade ablegt.
#   2. LoxBerry liefert /etc/sudoers.d/lbdefaults mit der Zeile
#          Defaults env_keep += "LBHOMEDIR LBPCGI LBPHTML LBPTEMPL LBPDATA
#                                LBPLOG LBPCONFIG LBPBIN ... LBPHTMLAUTH ..."
#      Diese Zeile gibt es genau zu diesem Zweck.
#
# Trotzdem wird hier abgesichert, und zwar aus einem anderen Grund als dem
# vermuteten: Faellt eine der Variablen doch einmal aus - von Hand
# angefasste sudoers, Aufruf des Skripts aus einer anderen Umgebung, ein
# kuenftiger Umbau -, dann wuerde aus
#       mkdir -p $LBPDATA/$pluginname
# ein "mkdir -p /loxoneicons". Als Benutzer loxberry scheitert das an den
# Rechten auf /, das Skript liefe aber weiter und meldete am Ende Erfolg.
# Ein leises Danebengreifen im Wurzelverzeichnis ist genau die Art Fehler,
# die man erst drei Monate spaeter bemerkt.
#
# Deshalb: Umgebung nehmen, wenn sie da ist; sonst aus $ARGV5 ableiten; und
# wenn selbst das nicht traegt, mit einer benannten Meldung abbrechen statt
# im Wurzelverzeichnis herumzufuhrwerken. Alle Pfade in Anfuehrungszeichen.
# ---------------------------------------------------------------------------
if [ -z "$ARGV5" ] || [ ! -d "$ARGV5" ]; then
	echo "<FAIL> Das Basisverzeichnis von LoxBerry wurde nicht uebergeben (\$5)."
	exit 1
fi
PDATA="${LBPDATA:-$ARGV5/data/plugins}/$pluginname"
PHTMLAUTH="${LBPHTMLAUTH:-$ARGV5/webfrontend/htmlauth/plugins}/$pluginname"
PBIN="${LBPBIN:-$ARGV5/bin/plugins}/$pluginname"

case "$PDATA$PHTMLAUTH$PBIN" in
	/*) : ;;
	*)  echo "<FAIL> Die Plugin-Pfade sind nicht absolut - Abbruch."; exit 1 ;;
esac

# The web interface serves the archives from ./files, which points at the
# plugin's data folder.
echo "<INFO> Linking the data folder into the web folder..."
mkdir -p "$PDATA" || {
	echo "<FAIL> Datenordner $PDATA liess sich nicht anlegen."
	exit 1
}
rm -f "$PHTMLAUTH/files"
ln -s "$PDATA" "$PHTMLAUTH/files"

# On an upgrade the icons already downloaded are put back by postupgrade.sh,
# which runs *after* this script. Downloading them again here would waste
# several minutes and a good deal of traffic on Loxone's servers.
# Die Sicherung liegt seit dieser Fassung unter data/plugins/<Ordner>.upgrade_sicherung
# und nicht mehr in der Ramdisk; der alte Ort wird noch mitgeprueft.
if [ -d "${LBPDATA:-$ARGV5/data/plugins}/${ARGV3}.upgrade_sicherung/loxone_icons" ] \
   || [ -d "/tmp/${ARGV1}_upgrade/data/loxone_icons" ]; then
	echo "<INFO> Upgrade detected. The icons already downloaded will be restored,"
	echo "<INFO> so there is nothing to download now."
	exit 0
fi

echo "<INFO> Downloading the icons from the Loxone website. This takes a minute or two..."
"$PBIN/download_icons.sh"
if [ $? -ne 0 ]; then
	echo "<WARNING> Downloading the icons did not finish cleanly. You can start it"
	echo "<WARNING> again at any time with the button on the plugin's web page."
else
	# Rueckgabewert 0 heisst nur "ohne Abbruch durchgelaufen", nicht "alles
	# da": download_icons.sh meldet fehlende Symbole als Warnung und endet
	# trotzdem mit 0. Bis 2.0.8 stand hier unbedingt "All icons downloaded." -
	# in WSL gemessen (18.09.2026, Pruefung-LoxoneIcons-2.0.8, Fall S1): bei
	# gescheitertem Abruf 0 von 1102 Symbolen und trotzdem diese Zeile.
	# Gezaehlt wird, was letzter_lauf.json sagt (schreibe_stand()).
	LI_STAND=$(cat "$PDATA/letzter_lauf.json" 2>/dev/null)
	LI_DA=$(printf '%s' "$LI_STAND" | sed -n 's/.*"vorhanden":\([0-9][0-9]*\).*/\1/p')
	LI_SOLL=$(printf '%s' "$LI_STAND" | sed -n 's/.*"soll":\([0-9][0-9]*\).*/\1/p')
	if [ -z "$LI_DA" ] || [ -z "$LI_SOLL" ]; then
		echo "<WARNING> Ob Symbole angekommen sind, liess sich nicht feststellen"
		echo "<WARNING> ($PDATA/letzter_lauf.json fehlt oder ist unlesbar)."
	elif [ "$LI_DA" -eq 0 ]; then
		echo "<WARNING> Es ist kein einziges Symbol angekommen (0 von $LI_SOLL)."
		echo "<WARNING> Internetzugang des LoxBerry pruefen; der Abruf laesst sich"
		echo "<WARNING> jederzeit ueber die Schaltflaeche in der Plugin-Oberflaeche erneut anstossen."
	else
		echo "<OK> $LI_DA von $LI_SOLL Symbolen vorhanden."
		if [ "$LI_DA" -lt "$LI_SOLL" ]; then
			echo "<INFO> Welche fehlen, nennt der Reiter Test der Plugin-Oberflaeche."
		fi
	fi
fi

# Exit with Status 0
exit 0
