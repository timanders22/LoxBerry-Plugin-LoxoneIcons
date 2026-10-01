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

# ---------- Aktualisierung oder Neuinstallation (I1/I2) ----------
#
# Entscheidung des Hausherrn vom 29.09.2026, Nr. 1: zurueckgespielt wird nur,
# wenn die Marke aus preupgrade.sh VORHANDEN ist - kein Altersvergleich. Bis
# 2.0.10 kannte die Linie keine Marke: eine Neuinstallation holte beim ersten
# Seitenaufruf das Token einer liegengebliebenen Zweitschrift zurueck, und
# eine liegengebliebene Symbolsicherung liess dieses Skript "Upgrade
# detected" melden und nichts laden (in WSL gemessen, Pruefung 29.09.2026,
# Faelle C und G). Die Marke faellt beim Beenden dieses Skripts (trap), auch
# bei einem Abbruch; der Rueckgabewert bleibt erhalten.
MARKE="$ARGV5/data/plugins/$pluginname.upgrade_laeuft"
LI_UPGRADE=0
[ -n "$pluginname" ] && [ -f "$MARKE" ] && LI_UPGRADE=1
li_marke_weg() {
	li_rc=$?
	[ -n "$pluginname" ] && rm -f "$MARKE" 2>/dev/null
	return "$li_rc"
}
trap li_marke_weg EXIT

ZWEIT="$ARGV5/config/plugins/$pluginname.backup.json"
PCONFIG="${LBPCONFIG:-$ARGV5/config/plugins}/$pluginname"
SICHER="${LBPDATA:-$ARGV5/data/plugins}/${pluginname}.upgrade_sicherung"

# Traegt die Zweitschrift ein gueltiges Token? Dieselbe Regel wie li_config():
# ein JSON-Objekt, aktionstoken Hex mit 32 bis 128 Zeichen.
# Rueckgabe 0 ja, 1 nein, 2 nicht pruefbar (kein php).
li_zweit_brauchbar() {
	command -v php >/dev/null 2>&1 || return 2
	php -r '$d = json_decode((string) @file_get_contents($argv[1]), true);
		exit(is_array($d) && isset($d["aktionstoken"]) && is_string($d["aktionstoken"])
			&& preg_match("/^[0-9a-f]{32,128}\z/", $d["aktionstoken"]) === 1 ? 0 : 1);' -- "$1" 2>/dev/null
	case "$?" in
		0) return 0 ;;
		1) return 1 ;;
	esac
	return 2
}

if [ "$LI_UPGRADE" = 1 ]; then
	# Regeln/06: gesichert in preupgrade (hier: die Zweitschrift, die die
	# Oberflaeche NEBEN dem Konfigurationsordner fuehrt), zurueckgespielt in
	# postinstall. Bis 2.0.10 geschah das erst beim ersten Seitenaufruf.
	if [ -f "$ZWEIT" ] && [ ! -f "$PCONFIG/loxoneicons.json" ]; then
		li_zweit_brauchbar "$ZWEIT"
		case "$?" in
		0)
			mkdir -p "$PCONFIG" 2>/dev/null
			LI_NEU="$PCONFIG/loxoneicons.json.neu.$$"
			if ( umask 077 && cp "$ZWEIT" "$LI_NEU" ) 2>/dev/null && chmod 600 "$LI_NEU" 2>/dev/null \
			   && mv -f "$LI_NEU" "$PCONFIG/loxoneicons.json" 2>/dev/null \
			   && cmp -s "$ZWEIT" "$PCONFIG/loxoneicons.json"; then
				echo "<OK> Konfiguration (Aktionstoken) aus der Zweitschrift zurueckgespielt."
			else
				rm -f "$LI_NEU" 2>/dev/null
				echo "<WARNING> Die Konfiguration liess sich nicht aus der Zweitschrift zurueckspielen;"
				echo "<WARNING> die Oberflaeche versucht es beim ersten Aufruf erneut ($ZWEIT)."
			fi
			;;
		1)
			echo "<WARNING> Die Zweitschrift $ZWEIT traegt kein gueltiges Aktionstoken"
			echo "<WARNING> (etwa gekuerzt) und wurde nicht zurueckgespielt. Die Oberflaeche legt sie"
			echo "<WARNING> beim ersten Aufruf als .kaputt beiseite und legt ein neues Aktionstoken an."
			;;
		*)
			echo "<INFO> php fehlt - die Zweitschrift wird nicht hier, sondern beim ersten"
			echo "<INFO> Aufruf der Oberflaeche zurueckgespielt."
			;;
		esac
	fi
else
	# Neuinstallation (X-1, Verbesserungsbau 01.10.2026): preinstall.sh hat
	# Zweitschrift und Symbolsicherung einer frueheren Installation schon VOR
	# dem Kopieren nach <name>.alt gelegt und es einmal gemeldet; uninstall
	# raeumt die .alt ab, li_config() liest sie nie. Was jetzt neben dem
	# Plugin-Ordner liegt, hat DIESE Installation geschrieben: ein Seitenaufruf
	# zwischen Kopieren und postinstall.sh legt Konfiguration und Zweitschrift
	# an. Bis 2.0.12 legte dieser Zweig auch die beiseite und meldete sie als
	# Sicherung einer frueheren Installation (in WSL gemessen, Fall X1
	# NEU_SAUBER). Deshalb geschieht hier nichts mehr.
	:
fi

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
# I2: "Upgrade detected" nur mit der Marke - eine liegengebliebene Sicherung
# ist bei einer Neuinstallation schon von preinstall.sh nach .alt verschoben.
if [ "$LI_UPGRADE" = 1 ] && { [ -d "${LBPDATA:-$ARGV5/data/plugins}/${ARGV3}.upgrade_sicherung/loxone_icons" ] \
   || [ -d "/tmp/${ARGV1}_upgrade/data/loxone_icons" ]; }; then
	echo "<INFO> Upgrade detected. The icons already downloaded will be restored,"
	echo "<INFO> so there is nothing to download now."
	exit 0
fi

echo "<INFO> Downloading the icons from the Loxone website. This takes a minute or two..."
# a1 (Verbesserungsbau 01.10.2026): die Zahl wird nach JEDEM Lauf gelesen,
# nicht nur bei Rueckgabewert 0. Bis 2.0.12 stand bei rc 1 (abgebrochen oder
# kein einziges Symbol) und rc 2 (Luecken) nur die allgemeine Warnung da, und
# "kein einziges Symbol (0 von ...)" war seit 2.0.10 nicht mehr erreichbar.
# Rueckgabewert 0 heisst nur "ohne Abbruch durchgelaufen", nicht "alles da"
# (bis 2.0.8 stand hier unbedingt "All icons downloaded." - in WSL gemessen
# 18.09.2026, Pruefung-LoxoneIcons-2.0.8, Fall S1). Gezaehlt wird, was
# letzter_lauf.json sagt (schreibe_stand()); ein Stand, der aelter ist als
# dieser Aufruf, stammt nicht aus diesem Lauf und gilt nicht.
LI_T0=$(date +%s)
"$PBIN/download_icons.sh"
LI_RC=$?
LI_STAND=$(cat "$PDATA/letzter_lauf.json" 2>/dev/null)
LI_ENDE=$(printf '%s' "$LI_STAND" | sed -n 's/.*"ende":\([0-9][0-9]*\).*/\1/p')
LI_DA=$(printf '%s' "$LI_STAND" | sed -n 's/.*"vorhanden":\([0-9][0-9]*\).*/\1/p')
LI_SOLL=$(printf '%s' "$LI_STAND" | sed -n 's/.*"soll":\([0-9][0-9]*\).*/\1/p')
if [ -n "$LI_ENDE" ] && [ "$LI_ENDE" -lt "$LI_T0" ]; then
	LI_DA=""
	LI_SOLL=""
fi
if [ "$LI_RC" -ne 0 ]; then
	echo "<WARNING> Downloading the icons did not finish cleanly (return code $LI_RC)."
fi
if [ -z "$LI_DA" ] || [ -z "$LI_SOLL" ]; then
	echo "<WARNING> Ob Symbole angekommen sind, liess sich nicht feststellen"
	echo "<WARNING> ($PDATA/letzter_lauf.json fehlt, ist unlesbar oder stammt nicht aus diesem Lauf)."
elif [ "$LI_DA" -eq 0 ]; then
	echo "<WARNING> Es ist kein einziges Symbol angekommen (0 von $LI_SOLL)."
	echo "<WARNING> Internetzugang des LoxBerry pruefen."
elif [ "$LI_RC" -ne 0 ]; then
	echo "<WARNING> $LI_DA von $LI_SOLL Symbolen vorhanden. Welche fehlen, nennt der Reiter Test"
	echo "<WARNING> der Plugin-Oberflaeche."
else
	echo "<OK> $LI_DA von $LI_SOLL Symbolen vorhanden."
	if [ "$LI_DA" -lt "$LI_SOLL" ]; then
		echo "<INFO> Welche fehlen, nennt der Reiter Test der Plugin-Oberflaeche."
	fi
fi
if [ "$LI_RC" -ne 0 ]; then
	echo "<WARNING> You can start the download again at any time with the button on the plugin's web page."
fi

# Exit with Status 0
exit 0
