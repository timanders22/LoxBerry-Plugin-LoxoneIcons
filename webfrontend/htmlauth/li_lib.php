<?php
/**
 * LoxoneIcons NG - Unterbau der Oberflaeche
 *
 * Bis 2.0.4 war die Oberflaeche ein Perl-Programm (index.cgi mit
 * HTML::Template). Mit 2.0.5 folgt sie dem Hausstandard: fuenf Reiter-Regel
 * (hier vier - MQTT entfaellt, das Plugin liefert nichts an den Miniserver),
 * Formularmerkmal gegen fremde Absender, Einstellungen sichern und
 * zurueckspielen, Reiter "Test" als Selbstpruefung.
 *
 * Es gibt keinen unangemeldeten Endpunkt. Die Bibliothek liegt deshalb neben
 * der Oberflaeche in htmlauth/ und nicht in html/.
 *
 * Copyright 2023 Michael Schlenstedt (urspruengliches Werk, Apache-2.0);
 * Aenderungen: LoxoneIcons Plugin Authors, siehe NOTICE.
 */

/* ------------------------------------------------------------------ */
/* Pfade                                                               */
/* ------------------------------------------------------------------ */

/**
 * Die Pfade des Plugins.
 *
 * Reihenfolge der Quellen: die Konstanten aus loxberry_system.php, die
 * Umgebung, zuletzt die Lage dieser Datei. Installiert liegt sie unter
 * <home>/webfrontend/htmlauth/plugins/<ordner>/ - vier Stufen bis <home>.
 * Im ausgepackten Archiv liegt sie unter <plugin>/webfrontend/htmlauth/.
 * Welche Lage vorliegt, entscheidet ein Blick auf data/plugins; ohne diese
 * Pruefung liefe die Ableitung auf einem fremden Baum ins Leere (Regeln/03,
 * harte Systempfade: Tiefengrenze).
 *
 * $neu = true verwirft den Zwischenspeicher - noetig, nachdem
 * loxberry_system.php eingebunden wurde und die Konstanten da sind.
 */
function li_paths($neu = false)
{
    static $p = null;
    if ($p !== null && !$neu) {
        return $p;
    }
    $quelle = function ($konstante, $umgebung) {
        if (defined($konstante)) {
            $w = (string) constant($konstante);
            if ($w !== '') {
                return rtrim($w, '/\\');
            }
        }
        $w = getenv($umgebung);
        return ($w === false || $w === '') ? '' : rtrim($w, '/\\');
    };

    $home = $quelle('LBHOMEDIR', 'LBHOMEDIR');
    $ordner = $quelle('LBPPLUGINDIR', 'LBPPLUGINDIR');
    $installiert = dirname(dirname(dirname(dirname(__DIR__))));
    if ($home === '' && is_dir($installiert . '/data/plugins')
        && basename(dirname(__DIR__)) === 'plugins') {
        $home = $installiert;
    }
    if ($ordner === '') {
        $ordner = (basename(dirname(__DIR__)) === 'plugins') ? basename(__DIR__) : 'loxoneicons';
    }

    $archiv = dirname(dirname(__DIR__));     // <plugin>/webfrontend/htmlauth -> <plugin>
    $p = array('home' => $home, 'plugin' => $ordner);
    if ($home !== '') {
        $p['config']    = $quelle('LBPCONFIGDIR', 'LBPCONFIG');
        $p['data']      = $quelle('LBPDATADIR', 'LBPDATA');
        $p['log']       = $quelle('LBPLOGDIR', 'LBPLOG');
        $p['bin']       = $quelle('LBPBINDIR', 'LBPBIN');
        $p['templates'] = $quelle('LBPTEMPLATEDIR', 'LBPTEMPL');
        $vorgabe = array(
            'config'    => $home . '/config/plugins/' . $ordner,
            'data'      => $home . '/data/plugins/' . $ordner,
            'log'       => $home . '/log/plugins/' . $ordner,
            'bin'       => $home . '/bin/plugins/' . $ordner,
            'templates' => $home . '/templates/plugins/' . $ordner,
        );
        foreach ($vorgabe as $k => $v) {
            if ($p[$k] === '') {
                $p[$k] = $v;
            }
        }
        /* Im ausgepackten Archiv gibt es templates/ und bin/ nur dort. */
        if (!is_dir($p['templates'] . '/lang') && is_dir($archiv . '/templates/lang')) {
            $p['templates'] = $archiv . '/templates';
        }
        if (!is_dir($p['bin']) && is_dir($archiv . '/bin')) {
            $p['bin'] = $archiv . '/bin';
        }
        $p['zweitschrift'] = $home . '/config/plugins/' . $ordner . '.backup.json';
    } else {
        $p['config']       = '';
        $p['data']         = $archiv . '/data';
        $p['log']          = '';
        $p['bin']          = $archiv . '/bin';
        $p['templates']    = $archiv . '/templates';
        $p['zweitschrift'] = '';
    }
    $p['cfgdatei'] = $p['config'] === '' ? '' : $p['config'] . '/loxoneicons.json';
    $p['skript']   = $p['bin'] . '/download_icons.sh';
    return $p;
}

/* ------------------------------------------------------------------ */
/* Sprache und Ausgabe                                                 */
/* ------------------------------------------------------------------ */

function li_sprache()
{
    $p = li_paths();
    if ($p['home'] !== '') {
        $g = $p['home'] . '/config/system/general.json';
        if (is_file($g)) {
            $d = json_decode((string) @file_get_contents($g), true);
            if (is_array($d) && isset($d['Base']['Lang']) && $d['Base']['Lang'] === 'en') {
                return 'en';
            }
        }
    }
    return 'de';
}

/** Uebersetzung. Englisch ist die Rueckfallebene, danach der Schluessel. */
function li_t($schluessel)
{
    static $tab = null;
    if ($tab === null) {
        $tab = array();
        $verz = li_paths()['templates'] . '/lang/';
        foreach (array('en', li_sprache()) as $l) {
            $d = @parse_ini_file($verz . 'language_' . $l . '.ini', true);
            if (is_array($d)) {
                foreach ($d as $abschnitt => $werte) {
                    if (!is_array($werte)) {
                        continue;
                    }
                    foreach ($werte as $k => $v) {
                        $tab[$abschnitt . '.' . $k] = (string) $v;
                    }
                }
            }
        }
    }
    return isset($tab[$schluessel]) ? $tab[$schluessel] : $schluessel;
}

function li_e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Protokoll der Oberflaeche. Kurz gehalten; log/plugins liegt auf einer Ramdisk. */
function li_log($text)
{
    $p = li_paths();
    if ($p['log'] === '') {
        return;
    }
    if (!is_dir($p['log'])) {
        @mkdir($p['log'], 0775, true);
    }
    $f = $p['log'] . '/oberflaeche.log';
    clearstatcache(true, $f);
    if (is_file($f) && filesize($f) > 131072) {
        $z = @file($f, FILE_IGNORE_NEW_LINES);
        @file_put_contents($f, implode("\n", array_slice(is_array($z) ? $z : array(), -400)) . "\n");
    }
    @file_put_contents($f, date('Y-m-d H:i:s') . ' ' . $text . "\n", FILE_APPEND);
}

/* ------------------------------------------------------------------ */
/* Konfiguration                                                       */
/* ------------------------------------------------------------------ */

/**
 * Die Vorgaben - die einzige Stelle.
 *
 * Das Plugin hat genau eine Einstellung: das Aktionstoken. Aus ihm wird das
 * Formularmerkmal abgeleitet (li_formtoken). Weitere Einstellungen gibt es
 * nicht; ein Feld fuer "spaeter" wird nicht gebaut.
 */
function li_vorgaben()
{
    return array(
        'aktionstoken' => '',
    );
}

/** Taugt ein Wert fuer seinen Schluessel? Eine Positivliste je Schluessel. */
function li_wert_pruefen($schluessel, $wert)
{
    if (!is_string($wert)) {
        return false;
    }
    if ($schluessel === 'aktionstoken') {
        /* Leer ist erlaubt (dann wird eines erzeugt), sonst Hex, 32 bis 128
           Zeichen - so weit gefasst, wie Token dieses Plugins aussehen. */
        return $wert === '' || preg_match('/^[0-9a-f]{32,128}$/', $wert) === 1;
    }
    return false;
}

function li_token_erzeugen()
{
    try {
        return bin2hex(random_bytes(24));
    } catch (Exception $e) {
        return substr(hash('sha256', uniqid((string) mt_rand(), true) . microtime(true)), 0, 48);
    }
}

/**
 * Zustand der Konfiguration in dieser Anfrage: ok, neu, zweitschrift, kaputt,
 * ohne_ablage. Die Seite liest die Konfiguration mehrfach; nach einer Heilung
 * waere jede weitere Lesung "ok". Behalten wird deshalb der ERSTE Zustand, der
 * nicht "ok" ist - sonst saehe der Reiter Test die Heilung nie.
 */
function li_config_zustand($setzen = null)
{
    static $z = null;
    if ($setzen !== null && ($z === null || $z === 'ok')) {
        $z = $setzen;
    }
    return $z === null ? 'ok' : $z;
}

/** Liest eine JSON-Datei; Rueckgabe Feld oder null. */
function li_json_lesen($datei)
{
    if ($datei === '' || !is_file($datei)) {
        return null;
    }
    $d = json_decode((string) @file_get_contents($datei), true);
    return is_array($d) ? $d : null;
}

/**
 * Konfiguration lesen, vervollstaendigen, bei Bedarf aus der Zweitschrift
 * heilen und das Aktionstoken einmal erzeugen.
 *
 * Die Zweitschrift liegt NEBEN dem Konfigurationsordner
 * (config/plugins/<ordner>.backup.json). Den Ordner selbst raeumt LoxBerry
 * bei jedem Upgrade ab; die Zweitschrift daneben ueberlebt, und das Token
 * kommt beim ersten Seitenaufruf zurueck.
 *
 * Geheilt wird nach INHALT: nur eine Zweitschrift, die das Token in gueltiger
 * Form traegt, wird zurueckgestellt - einmal, mit Protokollzeile.
 */
function li_config()
{
    $p = li_paths();
    $vorg = li_vorgaben();
    if ($p['cfgdatei'] === '') {
        li_config_zustand('ohne_ablage');
        return $vorg;
    }
    $roh_da = is_file($p['cfgdatei']);
    $d = li_json_lesen($p['cfgdatei']);
    $zustand = 'ok';
    if ($d === null) {
        $zweit = li_json_lesen($p['zweitschrift']);
        if (is_array($zweit) && isset($zweit['aktionstoken'])
            && is_string($zweit['aktionstoken'])
            && preg_match('/^[0-9a-f]{32,128}$/', $zweit['aktionstoken']) === 1) {
            $d = $zweit;
            $zustand = 'zweitschrift';
            li_log('Konfiguration ' . ($roh_da ? 'unlesbar' : 'fehlte')
                   . ' - aus der Zweitschrift zurueckgestellt.');
        } else {
            $d = array();
            $zustand = $roh_da ? 'kaputt' : 'neu';
        }
    }
    $cfg = $d;
    $fehlten = array();
    foreach ($vorg as $k => $v) {
        if (!array_key_exists($k, $cfg)) {
            $cfg[$k] = $v;
            $fehlten[] = $k;
        }
    }
    $schreiben = ($zustand !== 'ok') || (bool) $fehlten;
    if (!is_string($cfg['aktionstoken']) || $cfg['aktionstoken'] === '') {
        $cfg['aktionstoken'] = li_token_erzeugen();
        $schreiben = true;
    }
    if ($schreiben) {
        if ($zustand === 'kaputt') {
            /* Eine kaputte Datei wird nicht still ueberschrieben: sie bleibt
               daneben liegen, damit man sie ansehen kann. */
            @rename($p['cfgdatei'], $p['cfgdatei'] . '.kaputt.' . date('Ymd_His'));
            li_log('Konfiguration war unlesbar und ohne brauchbare Zweitschrift - neu angelegt, alte Datei beiseitegelegt.');
        }
        if ($fehlten && $zustand === 'ok') {
            li_log('Konfiguration ergaenzt: ' . implode(', ', $fehlten));
        }
        li_config_schreiben($cfg);
    }
    li_config_zustand($zustand);
    return $cfg;
}

/** Schreibt Konfiguration und Zweitschrift; Rechte 0600 schon beim Anlegen. */
function li_config_schreiben(array $cfg)
{
    $p = li_paths();
    if ($p['cfgdatei'] === '') {
        return false;
    }
    /* Geprueft werden die bekannten Schluessel. Fremde bleiben stehen, wie sie
       gelesen wurden - sie werden im Reiter Test genannt, nicht geloescht. */
    foreach (array_keys(li_vorgaben()) as $k) {
        if (!array_key_exists($k, $cfg) || !li_wert_pruefen($k, $cfg[$k])) {
            return false;                       // fail closed: gar nichts schreiben
        }
    }
    if (!is_dir($p['config'])) {
        @mkdir($p['config'], 0775, true);
    }
    $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    foreach (array($p['cfgdatei'], $p['zweitschrift']) as $ziel) {
        if ($ziel === '') {
            continue;
        }
        $tmp = $ziel . '.tmp.' . getmypid();
        $fh = @fopen($tmp, 'c');
        if ($fh === false) {
            return false;
        }
        @chmod($tmp, 0600);
        $ok = ftruncate($fh, 0) && fwrite($fh, $json . "\n") !== false;
        fflush($fh);
        fclose($fh);
        if (!$ok || !@rename($tmp, $ziel)) {
            @unlink($tmp);
            return false;
        }
    }
    return true;
}

/** Fehlende und fremde Schluessel der Datei. Fremdes wird genannt, nicht geloescht. */
function li_cfg_lage()
{
    $vorg = li_vorgaben();
    $datei = li_json_lesen(li_paths()['cfgdatei']);
    if (!is_array($datei)) {
        $datei = array();
    }
    $fehlend = array_values(array_diff(array_keys($vorg), array_keys($datei)));
    $fremd = array_values(array_diff(array_keys($datei), array_keys($vorg)));
    sort($fehlend);
    sort($fremd);
    return array('fehlend' => $fehlend, 'fremd' => $fremd, 'anzahl' => count($vorg));
}

/* ------------------------------------------------------------------ */
/* Formularmerkmal und Wachposten                                      */
/* ------------------------------------------------------------------ */

function li_formtoken()
{
    $t = (string) li_config()['aktionstoken'];
    if ($t === '') {
        return '';                              // fail closed
    }
    return hash_hmac('sha256', 'formular-v1', $t);
}

function li_fmt()
{
    return '<input data-role="none" type="hidden" name="fmt" value="'
         . htmlspecialchars(li_formtoken(), ENT_QUOTES, 'UTF-8') . '">';
}

/** '' wenn die Anfrage durchgelassen wird, sonst der Grund. */
function li_wachposten()
{
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    $soll = li_formtoken();
    $ist = isset($_POST['fmt']) ? $_POST['fmt'] : null;
    if ($soll === '') {
        return li_t('WACHE.KEIN_TOKEN');
    }
    if (!is_string($ist) || $ist === '') {
        return li_t('WACHE.FEHLT');
    }
    if (!hash_equals($soll, $ist)) {
        li_log('Ein Formular ohne gueltiges Merkmal wurde abgewiesen.');
        return li_t('WACHE.FALSCH');
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Sichern und zurueckspielen                                          */
/* ------------------------------------------------------------------ */

/** Die Sicherung - aus den Vorgaben gesiebt, mit lesbarem Kopf. */
function li_sicherung_bauen()
{
    $cfg = li_config();
    $aus = array(
        '_hinweis' => 'LoxoneIcons NG - Einstellungen. Enthaelt das Aktionstoken der Anlage: wie ein Passwort behandeln.',
        '_stand'   => date('Y-m-d H:i'),
    );
    foreach (array_keys(li_vorgaben()) as $k) {
        $aus[$k] = $cfg[$k];
    }
    return $aus;
}

/**
 * Eine Sicherung lesen. Rueckgabe array(Konfiguration|null, Beanstandungen[]).
 * Eine halb gueltige Datei aendert gar nichts; alle Beanstandungen werden
 * gesammelt. Schluessel mit '_' am Anfang sind der lesbare Kopf.
 */
function li_sicherung_lesen($roh)
{
    $daten = json_decode((string) $roh, true);
    if (!is_array($daten)) {
        return array(null, array(li_t('SICH.KEIN_JSON')));
    }
    $mangel = array();
    $neu = li_vorgaben();
    foreach ($daten as $k => $w) {
        $k = (string) $k;
        if ($k !== '' && $k[0] === '_') {
            continue;
        }
        if (!array_key_exists($k, $neu)) {
            $mangel[] = sprintf(li_t('SICH.FREMD'), $k);
            continue;
        }
        if (!li_wert_pruefen($k, $w) || $w === '') {
            $mangel[] = sprintf(li_t('SICH.WERT'), $k);
            continue;
        }
        $neu[$k] = $w;
    }
    $fehlend = array();
    foreach (array_keys(li_vorgaben()) as $k) {
        if (!array_key_exists($k, $daten)) {
            $fehlend[] = $k;
        }
    }
    if ($fehlend) {
        $mangel[] = sprintf(li_t('SICH.FEHLEND'), implode(', ', $fehlend));
    }
    return array($mangel ? null : $neu, $mangel);
}

/* ------------------------------------------------------------------ */
/* Symbole, Archive, Download                                          */
/* ------------------------------------------------------------------ */

/** Die drei Archive mit Groesse und Stand. */
function li_archive()
{
    $d = li_paths()['data'];
    $liste = array(
        array('datei' => 'loxone_icons/loxone_icons.zip',   'name' => 'ARCHIV.LOXONE'),
        array('datei' => 'weather_icons/weather_icons.zip', 'name' => 'ARCHIV.WETTER'),
        array('datei' => 'mixed_icons/mixed_icons.zip',     'name' => 'ARCHIV.GEMISCHT'),
    );
    foreach ($liste as $i => $a) {
        $pfad = $d . '/' . $a['datei'];
        clearstatcache(true, $pfad);
        $liste[$i]['da'] = is_file($pfad) && filesize($pfad) > 0;
        $liste[$i]['groesse'] = $liste[$i]['da'] ? filesize($pfad) : 0;
        $liste[$i]['zeit'] = $liste[$i]['da'] ? filemtime($pfad) : 0;
        $liste[$i]['url'] = 'files/' . $a['datei'];
    }
    return $liste;
}

/**
 * Laeuft ein Download? Beurteilt wird der Prozess, nicht die Sperrdatei:
 * eine Sperrdatei ohne lebenden Prozess ist ein Rest (download_icons.sh
 * uebergeht ihn selbst). Rueckgabe array(laeuft, pid).
 */
function li_lauf()
{
    $sperre = li_paths()['data'] . '/download.running';
    if (!is_file($sperre)) {
        return array(false, 0);
    }
    $pid = (int) trim((string) @file_get_contents($sperre));
    if ($pid <= 0) {
        return array(false, 0);
    }
    $cmd = @file_get_contents('/proc/' . $pid . '/cmdline');
    if (is_string($cmd) && strpos($cmd, 'download_icons.sh') !== false) {
        return array(true, $pid);
    }
    return array(false, $pid);
}

/** Startet download_icons.sh abgekoppelt. Rueckgabe '' oder der Grund. */
function li_starten($alles)
{
    $skript = li_paths()['skript'];
    if (!is_file($skript) || !is_executable($skript)) {
        return sprintf(li_t('START.KEIN_SKRIPT'), $skript);
    }
    list($laeuft, $pid) = li_lauf();
    if ($laeuft) {
        return sprintf(li_t('START.LAEUFT_SCHON'), $pid);
    }
    if (!function_exists('exec')) {
        return li_t('START.KEIN_EXEC');
    }
    $befehl = escapeshellarg($skript) . ($alles ? ' --force' : '') . ' > /dev/null 2>&1 &';
    $aus = array();
    $rc = 0;
    @exec($befehl, $aus, $rc);
    li_log('Download gestartet' . ($alles ? ' (alles neu)' : '') . ', Rueckgabe der Schale ' . $rc . '.');
    return $rc === 0 ? '' : sprintf(li_t('START.RC'), $rc);
}

/** Das Ergebnis des letzten Laufs aus letzter_lauf.json, oder null. */
function li_letzter_lauf()
{
    $d = li_json_lesen(li_paths()['data'] . '/letzter_lauf.json');
    if (!is_array($d) || !isset($d['ende'], $d['rc'], $d['soll'], $d['vorhanden'])) {
        return null;
    }
    if (!isset($d['fehlend']) || !is_array($d['fehlend'])) {
        $d['fehlend'] = array();
    }
    return $d;
}

/** Schnelle Zaehlung fuer die Fortschrittsanzeige - nur Dateien, kein Inhalt. */
function li_svg_zahl()
{
    $b = li_paths()['data'] . '/loxone_icons/svg/';
    $n = 0;
    foreach (array('filled', 'outlined') as $art) {
        $g = glob($b . $art . '/*.svg');
        $n += is_array($g) ? count($g) : 0;
    }
    return $n;
}

/** Die Symbolliste aus download_icons.sh: array(Anzahl je Ausfuehrung, erste Adresse). */
function li_symbolliste()
{
    $s = (string) @file_get_contents(li_paths()['skript']);
    $anzahl = 0;
    if (preg_match('/^icons=\((.*?)^\)/ms', $s, $m)) {
        $anzahl = preg_match_all('/^[a-z0-9][a-z0-9.\-]*\.svg$/m', $m[1], $x);
    }
    $url = '';
    $erstes = isset($x[0][0]) ? $x[0][0] : '';
    if ($erstes !== '' && preg_match('/^URLFILLED="(https:\/\/[^"]+)"/m', $s, $u)) {
        $url = $u[1] . '/' . $erstes;
    }
    return array((int) $anzahl, $url);
}

/** Reste frueherer Fassungen und abgebrochener Abrufe. */
function li_reste()
{
    $b = li_paths()['data'] . '/loxone_icons/svg/';
    $n = 0;
    foreach (array('filled', 'outlined') as $art) {
        foreach (array('/*.svg.[0-9]*', '/*.teil') as $muster) {
            $g = glob($b . $art . $muster);
            $n += is_array($g) ? count($g) : 0;
        }
    }
    return $n;
}

/** Ein Programm im Suchpfad finden - ohne einen Prozess zu starten. */
function li_programm($name)
{
    $pfad = getenv('PATH');
    if ($pfad === false || $pfad === '') {
        $pfad = '/usr/local/bin:/usr/bin:/bin';
    }
    foreach (explode(PATH_SEPARATOR, $pfad) as $verz) {
        $k = rtrim($verz, '/\\') . '/' . $name;
        if ($verz !== '' && is_file($k) && is_executable($k)) {
            return $k;
        }
    }
    return '';
}

/**
 * Ist die Loxone-Seite erreichbar? Ein HEAD auf das erste Symbol der Liste,
 * drei Sekunden, 300 Sekunden zwischengespeichert. Rueckgabe
 * array(1|0|null, Text): null heisst "nicht feststellbar", kein Kreuz.
 */
function li_netzprobe($url)
{
    if ($url === '') {
        return array(null, li_t('TEST.NETZ_KEINE_ADRESSE'));
    }
    $cache = li_paths()['data'] . '/netzprobe.json';
    $c = li_json_lesen($cache);
    if (is_array($c) && isset($c['zeit'], $c['code']) && time() - (int) $c['zeit'] < 300) {
        $code = (int) $c['code'];
        $alter = time() - (int) $c['zeit'];
    } else {
        if (!function_exists('curl_init')) {
            return array(null, li_t('TEST.NETZ_KEIN_CURL'));
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'LoxBerry LoxoneIcons NG');
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $alter = 0;
        @file_put_contents($cache, json_encode(array('zeit' => time(), 'code' => $code)));
    }
    if ($code === 0) {
        return array(null, li_t('TEST.NETZ_KEINE_ANTWORT'));
    }
    return array($code === 200 ? 1 : 0, sprintf(li_t('TEST.NETZ_CODE'), $code, $url, $alter));
}

/* ------------------------------------------------------------------ */
/* Pruefzeilen, die die eigene Datei lesen                             */
/* ------------------------------------------------------------------ */

/** Reiterliste gegen Leiste und Bereiche - Mengen, nicht Anzahlen. */
function li_reiterprobe(array $reiter, $datei)
{
    $s = (string) @file_get_contents($datei);
    if ($s === '') {
        return array(null, 'nicht feststellbar - ' . basename($datei) . ' nicht lesbar');
    }
    preg_match_all('/data-ziel="(tab-[a-z0-9]+)"/', $s, $a);
    preg_match_all('/class="sm-seite[^"]*"[^>]*id="(tab-[a-z0-9]+)"/', $s, $b);
    $leiste = array_values(array_unique($a[1]));
    $bereiche = array_values(array_unique($b[1]));
    $soll = array_values($reiter);
    sort($leiste);
    sort($bereiche);
    sort($soll);
    if (!$soll) {
        return array(0, 'die Reiterliste ist leer');
    }
    if ($leiste !== $soll || $bereiche !== $soll) {
        return array(0, 'Liste ' . implode(',', $soll) . ' / Leiste ' . implode(',', $leiste)
                      . ' / Bereiche ' . implode(',', $bereiche));
    }
    $ohne = array();
    foreach ($soll as $id) {
        $q = preg_quote($id, '/');
        if (!preg_match('/class="sm-tab<\?= \$li_tab === \'' . $q . '\' \? \' sm-active\'/', $s)
            || !preg_match('/class="sm-seite<\?= \$li_tab === \'' . $q . '\' \? \' sm-active\'/', $s)) {
            $ohne[] = $id;
        }
    }
    if ($ohne) {
        return array(0, 'ohne serverseitige Auswahl: ' . implode(', ', $ohne));
    }
    return array(1, 'ja, alle ' . count($soll) . ' Reiter an Liste, Leiste und Bereichen, serverseitig ausgewaehlt');
}

/** Traegt jedes Formular das Merkmal? */
function li_formularprobe($datei)
{
    $s = (string) @file_get_contents($datei);
    if ($s === '') {
        return array(null, 'nicht feststellbar - ' . basename($datei) . ' nicht lesbar');
    }
    $gesamt = 0;
    $ohne = 0;
    if (preg_match_all('/<form\s/', $s, $y, PREG_OFFSET_CAPTURE)) {
        foreach ($y[0] as $f) {
            $gesamt++;
            $ende = strpos($s, '</form>', $f[1]);
            $blk = substr($s, $f[1], ($ende === false ? 400 : $ende - $f[1]));
            if (strpos($blk, 'li_fmt()') === false) {
                $ohne++;
            }
        }
    }
    if ($gesamt === 0) {
        return array(0, 'kein Formular gefunden - die Pruefung greift ins Leere');
    }
    if ($ohne > 0) {
        return array(0, 'nein - ' . $ohne . ' von ' . $gesamt . ' ohne Merkmal');
    }
    return array(1, 'ja, alle ' . $gesamt . ' Formulare');
}
