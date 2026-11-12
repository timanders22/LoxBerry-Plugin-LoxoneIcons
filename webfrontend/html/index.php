<?php
/**
 * Absichtlich ohne Inhalt (seit 2.0.16).
 *
 * Diese Datei verhindert, dass der Apache bei einem Aufruf von
 * /plugins/<ordner>/ das Verzeichnis auflistet (LoxBerry fuehrt den
 * html-Baum mit Options +Indexes; am Geraet gemessen 12.11.2026: bis 2.0.15
 * antwortete /plugins/loxoneicons/ mit einer Verzeichnisliste). Bauart
 * Intercom, Regeln/03.
 *
 * Die eigentliche Oberflaeche liegt unter webfrontend/htmlauth/.
 */
header('HTTP/1.1 403 Forbidden');
header('Content-Type: text/plain; charset=utf-8');
echo "Hier gibt es nichts zu sehen. Die Oberflaeche des Plugins liegt im\n"
   . "angemeldeten Bereich von LoxBerry unter Plugins.\n";
