# LoxBerry-Plugin-LoxoneIcons

LoxBerry plugin which downloads the icons of the Loxone Touch Pure Flex
configurator and offers them as a ZIP archive.

## What it does

* Downloads all icons from the public Loxone website
  (`configurator.loxone.com`), both the filled and the outlined variant.
* Converts every icon to a 96×96 PNG in white, ready for use on a Touch
  Pure Flex.
* Packs everything into `loxone_icons.zip`.
* Ships a set of weather icons (SVG and PNG in seven colour variants) and
  a handful of mixed icons (waste bins, lawn mower, vacuum robot, …).

Download the archives on the plugin's web page and use the icons in your
Loxone projects.

## What it no longer does

Up to version 1.0.1 the plugin also built a complete `IconLibrary.zip`,
using the [loxicon](https://github.com/strobejb/loxicon/) script by James
Brown, and uploaded it to the Miniserver over FTP. **This stopped working
with Loxone Config V17** and was removed in version 2.0.0 together with
everything that only existed to support it: the elFinder file manager, the
Python dependency and the FTP transfer to the Miniserver.

If you need that functionality, use version 1.0.1 with Loxone Config V16
or older.

## Requirements

* LoxBerry 2.2.0 or newer
* `librsvg2-bin` (for `rsvg-convert`) and `p7zip-full` — both are
  installed automatically

## Notes on the icons

All Loxone icons are © Loxone. No icon is bundled with this plugin; they
are downloaded from Loxone's public website during installation. Use them
in your Loxone projects, but respect the copyright and do not use them
anywhere else.

## Refreshing the icon set

Loxone adds new icons from time to time. The plugin's web page has two
buttons:

* **Refresh icons** — only fetches what is missing. Takes seconds.
* **Download everything again** — throws the current set away and starts
  over. Takes about 90 seconds on a Raspberry Pi 4.

The same job can be run from the command line:

```
/opt/loxberry/bin/plugins/loxoneicons/download_icons.sh [--force]
```

The list of icons is hard-coded in that script. When Loxone publishes new
ones, refresh it with the command given in the comment at the top of the
icon list.

## Fassung 2.0.1 — Prüfbericht und Härtung

### Der gemeldete „Blocker" ist keiner

Ein Prüfer meldete, `$LBPDATA`, `$LBPHTMLAUTH` und `$LBPBIN` seien in
`postinstall.sh` leer, weil LoxBerry sie nicht exportiere und `/etc/environment`
nicht eingebunden sei; `mkdir -p $LBPDATA/$pluginname` werde daher zu
`mkdir -p /loxoneicons` und scheitere.

**Nachgeprüft — die Variablen sind gesetzt.** Zwei Belege:

1. `plugininstall.pl` ruft das Skript so auf:
   `sudo -n -u loxberry "$script" ...`. Auf Debian enthält `/etc/pam.d/sudo`
   die Zeile `session required pam_env.so readenv=1` — **sudo liest
   `/etc/environment`**, dort legt LoxBerry die Pfade ab.
2. LoxBerry liefert `system/sudoers/lbdefaults` mit:

       Defaults env_keep += "LBHOMEDIR LBPCGI LBPHTML LBPTEMPL LBPDATA
                             LBPLOG LBPCONFIG LBPBIN ... LBPHTMLAUTH ..."

   Diese Zeile existiert genau dafür, die Variablen über `sudo` zu retten.

Dass es so ist, zeigt auch die Gegenprobe: sämtliche mitgelieferten
LoxBerry-Beispielskripte benutzen `$LBPCONFIG`, `$LBPDATA` und so weiter ohne
jedes `source` — wären sie leer, wäre jedes zweite Plugin im Ökosystem kaputt.

### Abgesichert wurde trotzdem — aus einem anderen Grund

Die Kritik trifft einen anderen, realen Punkt: **wenn** eine der Variablen
einmal ausfällt (von Hand angefasste `sudoers`, Aufruf aus einer anderen
Umgebung, ein künftiger Umbau), dann greift das Skript still im
Wurzelverzeichnis daneben, läuft weiter und meldet am Ende Erfolg. Genau diese
Sorte Fehler bemerkt man erst Monate später.

`postinstall.sh` nimmt jetzt die Umgebung, wenn sie da ist, leitet sonst aus
`$5` ab, bricht mit benannter Meldung ab, wenn selbst das nicht trägt, und
setzt alle Pfade in Anführungszeichen. In drei Durchläufen gegengeprüft: ohne
die Variablen, mit ihnen, und ohne `$5`.

### Der Hinweis zum Perl-Backend war richtig

`system("... &")` gefolgt von `if ($?)` prüft nichts: durch das `&` endet die
Shell sofort, `$?` trägt nur, ob sie sich starten ließ. Die Prüfung meldete
immer Erfolg — auch dann, wenn `download_icons.sh` gar nicht vorhanden war.
Geprüft wird jetzt, was sich hier überhaupt prüfen lässt: ob das Skript da und
ausführbar ist. Wie der Lauf ausgeht, holt die Oberfläche ohnehin über
`status()` und die Sperrdatei `download.running` ab — der richtige Weg für
einen Vorgang, der Minuten dauert.

### Weitere Funde

- **`uninstall/uninstall` fehlte.** Beim Deinstallieren blieben
  `data/plugins/<ordner>.upgrade_sicherung` (je nach Bestand einige Megabyte,
  liegt absichtlich *neben* dem Datenordner) und ein noch laufender Download
  liegen. Das neue Skript beendet den Download über die PID aus der
  Sperrdatei — nicht über `pkill -f`, das auch einen Editor mit offener Datei
  träfe.
- **`plugin.cfg` trug noch Platzhalter** `<IHR-KONTO>/<IHR-REPOSITORY>` in
  beiden Update-Adressen, dazu `ARCHITECTURE=""` (die Anführungszeichen
  gehören nicht zum Wert) und `LB_MINIMUM=2.2.0`, obwohl das Plugin
  `libs/bashlib/loxberry_log.sh` benutzt — das trägt erst ab LoxBerry 3.
- **Autorkennung** auf die Abspaltung umgestellt; Michael Schlenstedt steht
  weiterhin unten unter *Herkunft*.

### Offen: die Oberfläche

Der Hausstandard verlangt fünf Reiter, `sm-`-Klassen, `data-role="none"` und
die Knopf-Farbregel. Diese Oberfläche ist eine **Perl-CGI mit
HTML::Template** und zwei Reitern. Sie funktioniert einwandfrei — `index.cgi`
füllt das Template über `readlanguage()`, beide Sprachdateien sind
deckungsgleich (21 Schlüssel), keiner fehlt, keiner ist unbenutzt (die drei
`ARCHIVE_*` werden dynamisch über `$L{"DOWNLOAD.$a->{label}"}` gebildet).

Sie auf den Hausstandard zu bringen heißt: Neuschreiben in PHP. Das ist ein
eigener Umbau, kein Aufräumen — und er ist hier bewusst **nicht** miterledigt
worden.

## Aufgeräumt

### Jedes Bild lag doppelt im Paket

`data/weather_icons/` enthielt die **169 Wettersymbole** sowohl in
`weather_icons.zip` als auch daneben entpackt unter `png/Color`, `png/Dark`,
`png/Flat`, `png/Green`, `png/Light`, `png/Realistic`, `png/Silver` und `svg/`.
Dasselbe bei `data/mixed_icons/`: die zehn SVG lagen im ZIP **und** in `svg/`.

Nachgeprüft, bevor gelöscht wurde: die 169 Wettersymbole aus dem ZIP sind mit
den daneben liegenden **bytegleich**, Dateiliste identisch. Und die Oberfläche
bietet ausschließlich die drei ZIP-Archive zum Herunterladen an
(`index.cgi`, `./files/<archiv>.zip`) — auf die entpackten Ordner greift keine
Zeile Code zu.

**179 Dateien entfernt, das Paket schrumpft von 2,9 MB auf 1,4 MB.**

### Dabei aufgefallen: ein Dateiname im mixed-Archiv

Der Ordner `svg/` enthielt `tonne_gruen.svg`, das ZIP dagegen
`tonne_grünb.svg` — **bytegleicher Inhalt**, aber mit Umlaut und einem
überzähligen „b". Offenbar war der Name schon einmal korrigiert worden, ohne
das Archiv neu zu bauen; ausgeliefert wurde weiter die alte Fassung.

`mixed_icons.zip` ist deshalb neu erzeugt worden — mit dem korrigierten,
rein aus ASCII bestehenden Namen. Umlaute in ZIP-Einträgen hängen für ihre
Darstellung vom Packprogramm ab und landen hier am Ende in Loxone Config.
Geprüft: zehn Bilder vorher wie nachher, Inhalte unverändert, alle Namen ASCII,
Archiv fehlerfrei.

### Sicherung beim Update

`preupgrade.sh` kopierte den heruntergeladenen Iconsatz nach
`/tmp/$ARGV1\_upgrade` — das ist auf dem LoxBerry eine **Ramdisk**. Der Satz von
Loxone umfasst mehrere tausend Dateien; ihn in den Arbeitsspeicher zu kopieren
kann auf einem kleinen Raspberry Pi den Speicher füllen, und bei einem Neustart
mitten im Update wäre er weg (das erneute Herunterladen dauert Minuten und
belastet die Server von Loxone).

Jetzt wird er unter `data/plugins/<Ordner>.upgrade_sicherung` beiseitegelegt,
und zwar per `mv` statt `cp`: innerhalb desselben Dateisystems ist das ein
Umbenennen — augenblicklich und ohne zusätzlichen Platzbedarf. Schlägt das fehl
(anderes Dateisystem), wird ersatzweise kopiert. Der alte Ort wird beim Update
von 2.0.0 noch mitgelesen. Nebenbei `${ARGV1}_upgrade` statt `$ARGV1\_upgrade`.

### Kleinigkeiten

- `data/loxone_icons/.dummy` (0 Byte, kommentarlos) heißt jetzt `.gitkeep` und
  erklärt, wozu es da ist: den sonst leeren Ordner im Paket zu halten, in den
  `download_icons.sh` die Icons von Loxone lädt.
- `.gitignore` ergänzt — sie schließt den heruntergeladenen Iconsatz aus (die
  Icons gehören Loxone, das Plugin besorgt sie auf dem Zielsystem) und die
  entpackten Zweitfassungen, damit sie nicht zurückkommen.

### Nachgetragen

- **`icons/icon.svg`** ergänzt — es lagen nur die vier PNG vor. Nach Hausstandard:
  runde Scheibe in LoxBerry-Grün, weißes Motiv, ein 3×3-Raster mit
  hervorgehobener Mittelkachel als Sinnbild einer Symbolsammlung. Bewusst **kein**
  nachgezeichnetes Loxone-Symbol und keine fremde Wortmarke — die Icons selbst
  gehören Loxone, das Plugin holt sie nur ab.
- **Die Update-Adressen zeigten auf `mschlenstedt`**, also auf das Repository des
  ursprünglichen Autors. Da dieses Plugin als Abspaltung unter eigenem Namen
  erscheinen soll, ist das eine Falle: LoxBerry vergleicht Fassungen als **Zahl**
  und lädt jede höhere Nummer nach — eine dort veröffentlichte 2.0.1 oder gar
  eine Datumsnummer wie `2021.05.14` würde diese Fassung samt aller Änderungen
  stillschweigend ersetzen.

  `plugin.cfg`, `release.cfg` und `prerelease.cfg` zeigen deshalb auf das
  Repository dieser Abspaltung. Die fehlende `prerelease.cfg` ist ergänzt.
  Erledigt in 2.0.2 — siehe unten.

## Fassung 2.0.2 — Auto-Update eingeschaltet

Das eigene Repository existiert jetzt, damit fallen die letzten offenen Punkte
aus 2.0.1 weg:

- **`AUTOMATIC_UPDATES` steht wieder auf `true`**, die Adressen zeigen auf
  `timanders22/LoxBerry-Plugin-LoxoneIcons`. Die Falle, vor der der Kommentar
  in `plugin.cfg` warnte — Adressen auf ein fremdes Repository —, besteht nicht
  mehr.
- **`LICENSE` und `NOTICE` nachgereicht.** Die Quelldateien tragen
  Apache-Kopfzeilen, am Wurzelpunkt lag aber keine Lizenzdatei — auch im
  Original nicht. Abschnitt 4 a der Apache-Lizenz verlangt, dass Empfänger eine
  Kopie erhalten; Abschnitt 4 b die Kennzeichnung der Änderungen, die jetzt in
  `NOTICE` steht.

### Kein neuer Plugin-Name

Die Frage stand offen, ob die Abspaltung einen eigenen Namen braucht. Sie
braucht keinen:

- **Die Kennung ist schon eigenständig.** Unter `[AUTHOR]` steht seit 2.0.0
  eine Projektkennung, nicht mehr der Originalautor. LoxBerry bildet die
  Kennzahl eines Plugins aus `[AUTHOR] NAME`, `EMAIL` und `[PLUGIN] NAME` —
  beide Fassungen sind dadurch bereits unterscheidbar und ließen sich
  nebeneinander installieren.
- **Es gibt nichts Geteiltes.** Alle Pfade leiten sich aus dem Ordner ab
  (`data/`, `log/`, `bin/` unter `plugins/<Ordner>`). Kein Systemdienst, keine
  Regeldatei in `/etc`, kein fester Port, kein Endpunkt für Loxone. Ein eigener
  Ordnername brächte hier nichts, was die Kennung nicht schon leistet — er
  würde nur einen bestehenden Bestand zur Neuinstallation zwingen.

`NAME` und `FOLDER` bleiben deshalb `loxoneicons`.

## Herkunft und Lizenz

Grundlage ist [LoxBerry-Plugin-LoxoneIcons von **Michael Schlenstedt**](https://github.com/mschlenstedt/LoxBerry-Plugin-LoxoneIcons),
Apache-Lizenz 2.0. Die Copyright-Vermerke stehen unverändert in den
übernommenen Dateien; die Liste der Änderungen in `NOTICE`.

Die Loxone-Symbole gehören Loxone und liegen **nicht** im Paket —
`bin/download_icons.sh` holt sie zur Laufzeit ab. Im Paket liegen nur die
beiden Zusatzsammlungen aus dem ursprünglichen Werk.

