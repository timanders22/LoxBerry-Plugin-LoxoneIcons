# LoxoneIcons NG — LoxBerry-Plugin

Holt die Symbole des Loxone-Konfigurators für den Touch Pure Flex von der
öffentlichen Loxone-Seite und stellt sie auf dem LoxBerry als Archiv zum
Herunterladen bereit.

LoxoneIcons NG ist eine eigenständig weitergeführte Abspaltung von
[LoxBerry-Plugin-LoxoneIcons](https://github.com/mschlenstedt/LoxBerry-Plugin-LoxoneIcons)
(Michael Schlenstedt). Seit 2.0.5 trägt sie den Titel „NG“, weil Oberfläche
und Verhalten vom Original abweichen. Die interne Kennung bleibt `loxoneicons`
— bestehende Installationen werden wie bisher aktualisiert.

## Was das Plugin tut

* Es lädt alle Symbole von `configurator.loxone.com`, gefüllt und umrissen,
  als SVG.
* Es wandelt jedes Symbol in ein PNG mit 96 × 96 Pixeln in Weiß um.
* Es packt alles in `loxone_icons.zip`.
* Es liefert zwei Sammlungen aus dem ursprünglichen Werk mit: 21 Wettersymbole
  (SVG, dazu PNG in sieben Farbvarianten) und zehn gemischte Symbole
  (Mülltonnen, Rasenroboter, Saugroboter, Pool).

Das Plugin liefert keine Werte an den Miniserver und verändert weder die
Projektdatei noch den Miniserver.

## Was es nicht mehr tut

Bis Fassung 1.0.1 baute das Plugin zusätzlich eine vollständige
`IconLibrary.zip` mit dem Skript [loxicon](https://github.com/strobejb/loxicon/)
von James Brown und spielte sie per FTP auf den Miniserver. Nach der
Beschreibung von Fassung 2.0.0 trägt dieser Weg ab Loxone Config 17 nicht mehr;
er wurde samt Dateimanager, Python-Abhängigkeit und FTP-Übertragung entfernt.

## Voraussetzungen

* LoxBerry 3.0.0 oder neuer (`libs/bashlib/loxberry_log.sh`)
* `librsvg2-bin` (für `rsvg-convert`) und `p7zip-full` — beide installiert
  LoxBerry mit dem Plugin
* Internetzugang des LoxBerry

## Oberfläche

| Reiter | Inhalt |
|---|---|
| Einstellungen | die drei Archive mit Größe und Stand, Abruf starten („Fehlendes nachladen“ oder „Alles löschen und neu laden“ mit Bestätigungshaken), Einstellungen sichern und zurückspielen |
| Einbindung in Loxone | was in den Archiven liegt, und was dieses Plugin in Loxone Config nicht tut |
| Test | Selbstprüfung: Reiter und Formulare, Konfiguration und Zweitschrift, Abrufskript und Programme, Symbolzahl gegen die Liste, Ergebnis des letzten Laufs, Reste abgebrochener Abrufe, Archiv, Erreichbarkeit der Loxone-Seite |
| Logdateien | die Protokolle des Plugins |

Jedes Formular trägt ein Merkmal gegen fremde Absender; es wird aus einem
Aktionstoken abgeleitet, das beim ersten Aufruf entsteht und neben dem
Konfigurationsordner eine Zweitschrift hat.

## Den Symbolsatz erneuern

Von der Kommandozeile geht dasselbe wie mit den Knöpfen:

```
<LoxBerry-Wurzel>/bin/plugins/loxoneicons/download_icons.sh [--force]
```

Ohne `--force` wird nur geholt, was fehlt oder unvollständig ist. Das Ergebnis
jedes Laufs steht in `data/plugins/loxoneicons/letzter_lauf.json`; der Reiter
Test liest es.

Die Symbolliste steht im Skript. Sie lässt sich nicht mehr automatisch
erneuern: Die Verzeichnisseite bei Loxone antwortet seit spätestens September
2026 mit „403 Forbidden“, während die einzelnen Dateien weiter ausgeliefert
werden. Neue Symbole müssen mit Namen in die Liste. Sieben Symbole (`awning-*`)
gibt es bei Loxone nur gefüllt, nicht umrissen.

## Urheberrecht der Symbole

Alle Loxone-Symbole sind © Loxone. Keines liegt im Paket; sie werden auf dem
LoxBerry von der öffentlichen Loxone-Seite geholt. Ihre Verwendung richtet sich
nach den Bedingungen von Loxone.

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

## Fassung 2.0.5 — LoxoneIcons NG: Hausstandard, abgebrochene Downloads

- **Titel „LoxoneIcons NG“.** Der Abschnitt zu 2.0.2 oben begründet, warum
  kein neuer *Plugin-Name* nötig ist — das gilt weiter: `NAME`, `FOLDER` und
  die Kennung unter `[AUTHOR]` bleiben. Geändert ist nur der angezeigte Titel
  (`TITLE`), damit die Abspaltung in der Plugin-Verwaltung vom Original zu
  unterscheiden ist.
- **Neue Oberfläche in PHP statt Perl.** `index.cgi` und
  `templates/settings.html` sind ersetzt durch `index.php` und `li_lib.php`:
  vier Reiter (Einstellungen, Einbindung in Loxone, Test, Logdateien),
  Formularmerkmal gegen fremde Absender, Einstellungen sichern und
  zurückspielen, Selbstprüfung. Nach dem Quelltext von `index.cgi` 2.0.4
  (gelesen, nicht am Gerät ausgelöst) genügte dort eine Anfrage mit
  `ajax=refresh&force=1` ohne jedes Merkmal, um den ganzen Symbolsatz zu
  löschen und neu zu laden — auch von einer fremden Seite im Browser eines
  angemeldeten Bedieners. Jetzt
  weist der Wachposten solche Anfragen ab, und „Alles löschen“ verlangt
  zusätzlich einen Haken.
- **Ergebnis jedes Laufs** in `letzter_lauf.json` (Zeitpunkt, Rückgabewert,
  Zahl der vollständigen Dateien, fehlende Symbole mit Namen).
- **Hilfetext berichtigt:** „das Format, das der Touch Pure Flex erwartet“
  und „das Protokoll nennt die Adresse“ waren nicht belegt bzw. falsch.

- **Eine leere oder abgeschnittene SVG-Datei blieb für immer liegen.**
  `download_icons.sh` rief `wget -P` auf, und `wget -P` überschreibt keine
  vorhandene Datei: der neue Abruf landete als `8-ball.svg.1` daneben, beim
  nächsten Lauf als `.svg.2`, und diese Dateien gingen mit ins ZIP-Archiv.
  Eine abgeschnittene Datei galt außerdem als vorhanden, weil nur „größer als
  null" geprüft wurde. Am LoxBerry nachgestellt (wget 1.25.0): 2.0.4 ließ beide
  Schäden stehen und legte zwei Zusatzdateien ins Archiv, 2.0.5 stellt beide
  Dateien byte-gleich wieder her.
  Jetzt gilt eine SVG-Datei nur als vorhanden, wenn sie mit `</svg>` endet;
  jeder Abruf schreibt zuerst nach `<name>.teil` und benennt erst bei Erfolg
  um; Reste der alten Form (`*.svg.1`, `*.svg.2` …) werden entfernt und das
  Archiv neu gebaut.
- **Deinstallation: der Rückfallpfad zeigte eine Ebene zu hoch.** LoxBerry legt
  das Skript unter `data/system/uninstall/` ab, nicht unter
  `data/plugins/<Ordner>/uninstall/`. Der Rückfall greift nur, wenn LoxBerry
  kein Basisverzeichnis übergibt — dann fand er die Upgrade-Sicherung nicht.
- **Die Liste der Symbole lässt sich nicht mehr automatisch erneuern.** Die
  Verzeichnisseite bei Loxone antwortet mit 403; der Hinweis darauf ist
  berichtigt. Sieben Symbole (`awning-*`) gibt es bei Loxone nur gefüllt, nicht
  umrissen — die Warnungen dazu sind erwartet.

## Fassung 2.0.7 — eine Prozessnummer ist kein Ausweis

Anlass ist eine Bestandsmessung vom 18.09.2026
(`Bestand-2026-09-18/klasse-F-nachmessung`). Gemessen wurde in WSL Ubuntu an
einem nachgebauten LoxBerry-Baum; die Prüfstände, Protokolle und Eichungen
liegen unter `Pruefung-LoxoneIcons-2.0.7/`. Ins Netz ging dabei nichts.

- **Beim Deinstallieren wurde ein fremder Prozess beendet.** `uninstall`
  schickte das Signal an die Nummer aus der Sperrdatei `download.running`,
  sobald diese Nummer überhaupt lebte — ohne die Befehlszeile anzusehen. Eine
  Sperrdatei bleibt aber liegen, wenn ein Lauf hart abbricht, und das System
  vergibt Prozessnummern wieder. Gemessen mit einem Köder `sleep 900`, dessen
  Nummer in der Sperrdatei stand:
  `<OK> Laufenden Download beendet (PID 666810, aus der Sperrdatei).` — der
  Köder war danach tot. Jetzt geht **jede** Nummer, auch die aus der
  Sperrdatei, durch dieselbe argumentweise Prüfung, und zwar vor jedem Signal
  erneut — auch vor `kill -9`, denn zwischen den beiden Signalen kann ein
  Prozess enden und seine Nummer neu vergeben werden.

- **Der Rückfall über `/proc` hat nie getroffen.** Er verglich `argv[0]` mit
  dem Pfad von `download_icons.sh`. `argv[0]` ist bei einem über seine
  Shebang-Zeile gestarteten Skript aber **der Interpreter**; der eigene Pfad
  steht in `argv[1]`. Gemessen an einem laufenden `download_icons.sh`:
  `/bin/bash`, `<pfad>/download_icons.sh`, `--force`. Ein laufender Download
  **ohne** Sperrdatei überlebte die Deinstallation deshalb bisher unbemerkt
  und schrieb weiter in Ordner, die es nicht mehr gab. Jetzt wird `argv[0]`
  gegen den Interpreter, `argv[1]` gegen den eigenen Pfad (Symlinks aufgelöst,
  relativer Start über `/proc/<pid>/cwd`) und der Prozessbenutzer gegen den
  LoxBerry-Benutzer geprüft; als weiteres Argument gilt nur `--force`.

- **Ein zweiter Lauf blieb stehen.** Behandelt wurde nur die Nummer aus der
  Sperrdatei. Jetzt werden alle eigenen Läufe eingesammelt und beendet, und
  am Ende wird nachgesehen, ob wirklich keiner mehr läuft; bleibt einer übrig,
  sagt das `uninstall` als `<WARNING>` und endet mit Rückgabewert 1.

- **Eine veraltete Sperrdatei konnte das Plugin dauerhaft aussperren.**
  `download_icons.sh` lehnte jeden neuen Lauf ab, sobald *irgendein* Prozess
  mit der Nummer aus der Sperrdatei lebte:
  `Another run is already in progress (PID 672641). Giving up.` — dabei war
  das der Köder `sleep 900`. Symbole ließen sich dann weder ergänzen noch neu
  holen. Die Nummer geht jetzt durch dieselbe Prüfung; ein echter zweiter Lauf
  wird weiterhin abgewiesen (nachgemessen).

- **Die Oberfläche meldete „läuft schon" für einen Fremden.** `li_lauf()`
  entschied mit `strpos()` über die ganze Befehlszeile und hielt einen Köder,
  der den Dateinamen nur erwähnte, für einen Download — und verweigerte damit
  den Start. Auch diese Stelle prüft jetzt argumentweise.

Die Sperrdatei wird beim Deinstallieren in jedem Fall entfernt, auch eine
veraltete.

## Herkunft und Lizenz

Grundlage ist [LoxBerry-Plugin-LoxoneIcons von **Michael Schlenstedt**](https://github.com/mschlenstedt/LoxBerry-Plugin-LoxoneIcons),
Apache-Lizenz 2.0. Die Copyright-Vermerke stehen unverändert in den
übernommenen Dateien; die Liste der Änderungen in `NOTICE`.

Die Loxone-Symbole gehören Loxone und liegen **nicht** im Paket —
`bin/download_icons.sh` holt sie zur Laufzeit ab. Im Paket liegen nur die
beiden Zusatzsammlungen aus dem ursprünglichen Werk.

