<?php
/**
 * LoxoneIcons NG - Bedienoberflaeche
 *
 * Reihenfolge ist Bauvorschrift (VORLAGE_hausstandard.css.html):
 *   1. Bibliothek    2. LoxBerry-Rahmen einbinden    3. Konfiguration
 *   4. Wachposten    5. Reiterwahl    6. Handler samt Downloads
 *   7. erst dann lbheader()    8. HTML
 *
 * Copyright 2023 Michael Schlenstedt (urspruengliches Werk, Apache-2.0);
 * Aenderungen: LoxoneIcons Plugin Authors, siehe NOTICE.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/li_lib.php';

/* ---------- 2. LoxBerry-Rahmen ---------- */
$li_p = li_paths();
if ($li_p['home'] !== '' && is_file($li_p['home'] . '/libs/phplib/loxberry_system.php')) {
    require_once $li_p['home'] . '/libs/phplib/loxberry_system.php';
    if (is_file($li_p['home'] . '/libs/phplib/loxberry_web.php')) {
        require_once $li_p['home'] . '/libs/phplib/loxberry_web.php';
    }
    $li_p = li_paths(true);
}
$li_rahmen = class_exists('LBWeb', false);

/* ---------- 3. Konfiguration ---------- */
$li_cfg = li_config();
$li_meldungen = array();
$li_fehler = array();

/* ---------- 4. Wachposten ---------- */
$li_wache = li_wachposten();
if ($li_wache !== '') {
    $li_behalten = (isset($_POST['activetab']) && is_string($_POST['activetab'])) ? $_POST['activetab'] : null;
    $_POST = array();
    if ($li_behalten !== null) {
        $_POST['activetab'] = $li_behalten;
    }
    $li_fehler[] = $li_wache;
}
$li_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

/* ---------- 5. Reiterwahl ---------- */
$li_reiter = array('tab-settings', 'tab-loxone', 'tab-test', 'tab-log');
$li_tab = 'tab-settings';
if (isset($_POST['activetab']) && in_array((string) $_POST['activetab'], $li_reiter, true)) {
    $li_tab = (string) $_POST['activetab'];
} elseif (isset($_GET['form']) && in_array('tab-' . (string) $_GET['form'], $li_reiter, true)) {
    $li_tab = 'tab-' . (string) $_GET['form'];
}

/* ---------- 6. Handler ---------- */

/* Zustandsabfrage fuer die Fortschrittsanzeige. Fragt nur ab, veraendert
   nichts, und braucht deshalb kein Merkmal. */
if (!$li_post && isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    list($li_laeuft, $li_pid) = li_lauf();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array('laeuft' => $li_laeuft ? 1 : 0, 'svg' => li_svg_zahl()));
    exit;
}

$li_gestartet = false;
if ($li_post && isset($_POST['nachladen'])) {
    $li_grund = li_starten(false);
    if ($li_grund === '') {
        $li_meldungen[] = li_t('START.GESTARTET');
        $li_gestartet = true;
    } else {
        $li_fehler[] = $li_grund;
    }
}

if ($li_post && isset($_POST['alles_neu'])) {
    if (empty($_POST['bestaetigt'])) {
        $li_fehler[] = li_t('START.OHNE_HAKEN');
    } else {
        $li_grund = li_starten(true);
        if ($li_grund === '') {
            $li_meldungen[] = li_t('START.GESTARTET_ALLES');
            $li_gestartet = true;
        } else {
            $li_fehler[] = $li_grund;
        }
    }
}

/* Einstellungen sichern - ein Download, deshalb vor lbheader(). */
if ($li_post && isset($_POST['sichern'])) {
    $li_js = json_encode(li_sicherung_bauen(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($li_js !== false) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="loxoneicons_einstellungen_' . date('Ymd_His') . '.json"');
        echo $li_js . "\n";
        exit;
    }
    $li_fehler[] = li_t('SICH.SCHREIBFEHLER');
}

/* Einstellungen zurueckspielen. is_uploaded_file() zuerst, dann die Groesse. */
if ($li_post && isset($_POST['laden'])) {
    if (!isset($_FILES['sicherung']['tmp_name']) || !is_string($_FILES['sicherung']['tmp_name'])
        || !@is_uploaded_file($_FILES['sicherung']['tmp_name'])) {
        $li_fehler[] = li_t('SICH.KEINE_DATEI');
    } elseif ((int) $_FILES['sicherung']['size'] > 65536) {
        $li_fehler[] = li_t('SICH.ZU_GROSS');
    } else {
        list($li_neu, $li_mangel) = li_sicherung_lesen((string) @file_get_contents($_FILES['sicherung']['tmp_name']));
        if ($li_neu === null) {
            $li_fehler[] = li_t('SICH.ABGELEHNT') . ' ' . implode(' ', $li_mangel);
        } elseif (li_config_schreiben($li_neu)) {
            $li_cfg = li_config();
            $li_meldungen[] = li_t('SICH.UEBERNOMMEN');
            li_log('Einstellungen aus einer Sicherung zurueckgespielt.');
        } else {
            $li_fehler[] = li_t('SICH.SCHREIBFEHLER');
        }
    }
}

list($li_laeuft, $li_pid) = li_lauf();
$li_archive = li_archive();
$li_letzter = li_letzter_lauf();
list($li_listenzahl, $li_probe_url) = li_symbolliste();

/* ---------- 7. Rahmen ---------- */
if ($li_rahmen) {
    $li_titel = li_t('ALLGEMEIN.TITEL');
    if (class_exists('LBSystem', false) && method_exists('LBSystem', 'pluginversion')) {
        /* Ueber den Ordnernamen fragen (Regeln/03): ohne Argument haengt die
         * Antwort am ersten eingebundenen Skript - am Geraet gemessen
         * 17.09.2026: aus einem fremden Einstieg (php -r) NULL, mit dem
         * Ordnernamen die installierte Fassung. Installiert liegt diese Datei
         * unter webfrontend/html(auth)/plugins/<ordner>/. */
        $li_fassung = (string) LBSystem::pluginversion(basename(__DIR__));
        if ($li_fassung !== '') {
            $li_titel .= ' V' . $li_fassung;
        }
    }
    LBWeb::lbheader($li_titel, 'https://wiki.loxberry.de/plugins/loxoneicons/start', 'help.html');
}
?>
<style>
/* Hausstandard: eigener Behaelter, kein Schattenwurf, Reiter im Fluss */
.sm-wrap { max-width: 980px; margin: 0 auto; font-family: -apple-system, 'Segoe UI', Roboto, sans-serif; color: #333; }
.sm-wrap, .sm-wrap *, .sm-tabs, .sm-tabs * { text-shadow: none !important; }
.sm-wrap h2 { color: #6dac20; margin: 24px 0 10px; font-size: 1.15em; border-bottom: 2px solid #e0e0e0; padding-bottom: 6px; }
.sm-wrap h3 { color: #4f7d17; font-size: 1.0em; font-weight: 700; margin: 16px 0 2px; }
.sm-tabs { display: flex; gap: 4px; margin: 14px 0 0; border-bottom: 2px solid #6dac20; flex-wrap: wrap; }
.sm-tab { background: #eee; border: 1px solid #ccc; border-bottom: 0; border-radius: 8px 8px 0 0;
          padding: 9px 18px; font-size: 0.95em; color: #444 !important; text-decoration: none; display: inline-block; }
.sm-tab.sm-active { background: #6dac20; color: #fff !important; border-color: #6dac20; font-weight: 600; }
.sm-feld { margin: 14px 0; }
.sm-feld > label { display: block; font-weight: 600; font-size: 0.9em; color: #555; margin: 0 0 4px; }
.sm-feld .ui-input-text, .sm-feld .ui-select, .sm-feld .ui-textinput { max-width: 520px; }
.sm-feld .ui-input-text input, .sm-feld .ui-input-text textarea { font-size: 0.95em; }
.sm-hilfe { font-size: 0.85em; color: #555; margin: 4px 0 0; max-width: 640px; }
.sm-step { border: 1px solid #ddd; border-left: 4px solid #6dac20; background: #fafafa;
    border-radius: 6px; padding: 12px 14px; margin: 12px 0; font-size: 0.92em; line-height: 1.5; }
.sm-tbl { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 0.9em; }
.sm-tbl th, .sm-tbl td { border: 1px solid #ccc; padding: 5px 7px; text-align: left; vertical-align: top; }
.sm-tbl th { background: #eef3e6; font-weight: 600; }
.sm-mono { font-family: Consolas, "Courier New", monospace; background: #f0f0f0;
    padding: 1px 4px; border-radius: 3px; font-size: 0.94em; word-break: break-all; }
.sm-pre { background: #f4f4f4; border: 1px solid #ccc; padding: 10px; font-size: 0.85em;
    overflow: auto; margin: 8px 0; }
.sm-knopfreihe { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0 4px; align-items: stretch; }
.sm-knopfreihe form { margin: 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.sm-wrap .sm-knopfreihe .sm-btn, .sm-wrap a.sm-btn, .sm-wrap button.sm-btn {
    flex: 0 0 auto; min-width: 250px; text-align: center; display: inline-flex;
    align-items: center; justify-content: center; line-height: 1.25;
    padding: 10px 14px !important; border-radius: 6px !important;
    color: #fff !important; text-decoration: none !important; font-size: 0.92em;
    border: 0 !important; cursor: pointer; font-weight: 600 !important;
    text-shadow: none !important; box-shadow: none !important;
    opacity: 1 !important; margin: 0 !important; width: auto !important; }
.sm-kacheln { display: flex; flex-wrap: wrap; gap: 10px; margin: 10px 0; }
.sm-kachel { border: 1px solid #ddd; border-radius: 10px; padding: 10px 14px; min-width: 130px; }
.sm-kachel b { display: block; font-size: 1.35em; color: #33691e; }
.sm-legende { display: flex; flex-wrap: wrap; gap: 14px; margin: 10px 0 2px; font-size: 0.86em; color: #555; }
.sm-legende span { display: inline-flex; align-items: center; gap: 6px; }
.sm-punkt { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
.sm-wrap .sm-btn.sm-b-lesen   { background: #6dac20 !important; }
.sm-wrap .sm-btn.sm-b-technik { background: #546e7a !important; }
.sm-wrap .sm-btn.sm-b-aktion  { background: #e0620d !important; }
.sm-wrap .sm-btn.sm-b-lesen:hover,   .sm-wrap .sm-btn.sm-b-lesen:focus   { background: #5c9219 !important; color: #fff !important; }
.sm-wrap .sm-btn.sm-b-technik:hover, .sm-wrap .sm-btn.sm-b-technik:focus { background: #435962 !important; color: #fff !important; }
.sm-wrap .sm-btn.sm-b-aktion:hover,  .sm-wrap .sm-btn.sm-b-aktion:focus  { background: #b84f0a !important; color: #fff !important; }
.sm-punkt.sm-b-lesen   { background: #6dac20; }
.sm-punkt.sm-b-technik { background: #546e7a; }
.sm-punkt.sm-b-aktion  { background: #e0620d; }
.sm-seite { display: none; padding-top: 4px; }
.sm-seite.sm-active { display: block; }
.sm-hinweis { border: 1px solid #cfe3b0; background: #f2f8ea; border-radius: 6px;
    padding: 10px 12px; margin: 12px 0; font-size: 0.9em; }
.sm-warnung { border: 1px solid #f0c9a0; background: #fdf4ec; border-radius: 6px;
    padding: 10px 12px; margin: 12px 0; font-size: 0.9em; }
.sm-an  { color: #1a7f1a; font-weight: 700; }
.sm-aus { color: #b00000; font-weight: 700; }
.sm-breit { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 10px 0; }
.sm-breit .sm-tbl { margin: 0; min-width: 760px; }
.sm-wrap select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='9' viewBox='0 0 14 9'%3E%3Cpath d='M1 1l6 6 6-6' fill='none' stroke='%234f7d17' stroke-width='2'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    padding-right: 32px; cursor: pointer; }
.sm-tbl select { padding-right: 28px; background-position: right 7px center; }
.sm-grau { color: #777; }
</style>

<div class="sm-wrap">

<?php foreach ($li_meldungen as $li_m) { ?><div class="sm-hinweis"><?= li_e($li_m) ?></div><?php } ?>
<?php foreach ($li_fehler as $li_m) { ?><div class="sm-warnung"><?= li_e($li_m) ?></div><?php } ?>

<div class="sm-tabs">
	<a class="sm-tab<?= $li_tab === 'tab-settings' ? ' sm-active' : '' ?>" data-ziel="tab-settings"
	   href="index.php?form=settings"><?= li_e(li_t('REITER.EINSTELLUNGEN')) ?></a>
	<a class="sm-tab<?= $li_tab === 'tab-loxone' ? ' sm-active' : '' ?>" data-ziel="tab-loxone"
	   href="index.php?form=loxone"><?= li_e(li_t('REITER.LOXONE')) ?></a>
	<a class="sm-tab<?= $li_tab === 'tab-test' ? ' sm-active' : '' ?>" data-ziel="tab-test"
	   href="index.php?form=test"><?= li_e(li_t('REITER.TEST')) ?></a>
	<a class="sm-tab<?= $li_tab === 'tab-log' ? ' sm-active' : '' ?>" data-ziel="tab-log"
	   href="index.php?form=log"><?= li_e(li_t('REITER.LOG')) ?></a>
</div>

<!-- ================= Einstellungen ================= -->
<div class="sm-seite<?= $li_tab === 'tab-settings' ? ' sm-active' : '' ?>" id="tab-settings">

<div class="sm-legende">
	<span><i class="sm-punkt sm-b-lesen"></i> <?= li_e(li_t('LEGENDE.LESEN')) ?></span>
	<span><i class="sm-punkt sm-b-aktion"></i> <?= li_e(li_t('LEGENDE.AKTION')) ?></span>
</div>

<h2><?= li_e(li_t('EINST.H_ARCHIVE')) ?></h2>
<div class="sm-step"><?= li_t('EINST.WAS') ?></div>

<table class="sm-tbl">
	<tr><th><?= li_e(li_t('EINST.SP_ARCHIV')) ?></th><th><?= li_e(li_t('EINST.SP_GROESSE')) ?></th><th><?= li_e(li_t('EINST.SP_STAND')) ?></th><th></th></tr>
<?php foreach ($li_archive as $li_a) { ?>
	<tr>
		<td><?= li_e(li_t($li_a['name'])) ?></td>
<?php if ($li_a['da']) { ?>
		<td><?= li_e(number_format($li_a['groesse'] / 1048576, 1, ',', '.')) ?> MB</td>
		<td><?= li_e(date('Y-m-d H:i', $li_a['zeit'])) ?></td>
		<td><a data-role="none" class="sm-btn sm-b-lesen" href="<?= li_e($li_a['url']) ?>" download><?= li_e(li_t('EINST.K_HERUNTERLADEN')) ?></a></td>
<?php } else { ?>
		<td colspan="3" class="sm-grau"><?= li_e(li_t('EINST.NOCH_NICHT')) ?></td>
<?php } ?>
	</tr>
<?php } ?>
</table>

<h2><?= li_e(li_t('EINST.H_ABRUF')) ?></h2>
<div class="sm-kacheln">
	<div class="sm-kachel"><b id="li-svg"><?= (int) li_svg_zahl() ?></b><span><?= li_e(sprintf(li_t('EINST.KACHEL_SVG'), $li_listenzahl * 2)) ?></span></div>
	<div class="sm-kachel"><b id="li-lauf"><?= $li_laeuft ? li_e(li_t('EINST.LAEUFT')) : li_e(li_t('EINST.RUHT')) ?></b><span><?= li_e(li_t('EINST.KACHEL_LAUF')) ?></span></div>
	<div class="sm-kachel"><b><?= $li_letzter === null ? '&mdash;' : li_e(date('Y-m-d H:i', (int) $li_letzter['ende'])) ?></b><span><?= li_e(li_t('EINST.KACHEL_LETZTER')) ?></span></div>
</div>
<p class="sm-hilfe"><?= li_t('EINST.ABRUF_HILFE') ?></p>

<div class="sm-knopfreihe">
	<form action="index.php" method="post">
		<?php echo li_fmt(); ?>
		<input data-role="none" type="hidden" name="activetab" value="tab-settings">
		<button data-role="none" class="sm-btn sm-b-lesen" type="submit" name="nachladen" value="1"<?= $li_laeuft ? ' disabled' : '' ?>><?= li_e(li_t('EINST.K_NACHLADEN')) ?></button>
	</form>
	<form action="index.php" method="post">
		<?php echo li_fmt(); ?>
		<input data-role="none" type="hidden" name="activetab" value="tab-settings">
		<label class="sm-hilfe"><input data-role="none" type="checkbox" name="bestaetigt" value="1"> <?= li_e(li_t('EINST.HAKEN_ALLES')) ?></label>
		<button data-role="none" class="sm-btn sm-b-aktion" type="submit" name="alles_neu" value="1"<?= $li_laeuft ? ' disabled' : '' ?>><?= li_e(li_t('EINST.K_ALLES_NEU')) ?></button>
	</form>
</div>
<p class="sm-hilfe"><?= li_t('EINST.ALLES_HILFE') ?></p>

<h2><?= li_e(li_t('EINST.H_SICHERUNG')) ?></h2>
<div class="sm-hinweis"><?= li_t('EINST.SICH_ERKLAERUNG') ?></div>
<div class="sm-warnung"><?= li_t('EINST.SICH_WARNUNG') ?></div>
<div class="sm-knopfreihe">
	<form action="index.php" method="post">
		<?php echo li_fmt(); ?>
		<input data-role="none" type="hidden" name="activetab" value="tab-settings">
		<button data-role="none" class="sm-btn sm-b-lesen" type="submit" name="sichern" value="1"><?= li_e(li_t('EINST.K_SICHERN')) ?></button>
	</form>
	<form action="index.php" method="post" enctype="multipart/form-data">
		<?php echo li_fmt(); ?>
		<input data-role="none" type="hidden" name="activetab" value="tab-settings">
		<input data-role="none" type="file" name="sicherung" accept=".json">
		<button data-role="none" class="sm-btn sm-b-aktion" type="submit" name="laden" value="1"><?= li_e(li_t('EINST.K_ZURUECK')) ?></button>
	</form>
</div>
</div>

<!-- ================= Einbindung in Loxone ================= -->
<div class="sm-seite<?= $li_tab === 'tab-loxone' ? ' sm-active' : '' ?>" id="tab-loxone">
<h2><?= li_e(li_t('LOX.H')) ?></h2>
<div class="sm-step"><b>1.</b> <?= li_t('LOX.S1') ?></div>
<div class="sm-step"><b>2.</b> <?= li_t('LOX.S2') ?></div>
<div class="sm-step"><b>3.</b> <?= li_t('LOX.S3') ?>
<table class="sm-tbl">
	<tr><th><?= li_e(li_t('LOX.SP_ORDNER')) ?></th><th><?= li_e(li_t('LOX.SP_INHALT')) ?></th></tr>
	<tr><td class="sm-mono">loxone_icons/svg/filled</td><td><?= li_e(li_t('LOX.SVG_FILLED')) ?></td></tr>
	<tr><td class="sm-mono">loxone_icons/svg/outlined</td><td><?= li_e(li_t('LOX.SVG_OUTLINED')) ?></td></tr>
	<tr><td class="sm-mono">loxone_icons/png/filled</td><td><?= li_e(li_t('LOX.PNG_FILLED')) ?></td></tr>
	<tr><td class="sm-mono">loxone_icons/png/outlined</td><td><?= li_e(li_t('LOX.PNG_OUTLINED')) ?></td></tr>
	<tr><td class="sm-mono">weather_icons/svg, weather_icons/png/&lt;Farbe&gt;</td><td><?= li_e(li_t('LOX.WETTER')) ?></td></tr>
	<tr><td class="sm-mono">diverse_icons</td><td><?= li_e(li_t('LOX.GEMISCHT')) ?></td></tr>
</table>
</div>
<div class="sm-step"><b>4.</b> <?= li_t('LOX.S4') ?></div>
<div class="sm-step"><b>5.</b> <?= li_t('LOX.S5') ?></div>
</div>

<!-- ================= Test ================= -->
<div class="sm-seite<?= $li_tab === 'tab-test' ? ' sm-active' : '' ?>" id="tab-test">

<div class="sm-legende">
	<span><i class="sm-punkt sm-b-lesen"></i> <?= li_e(li_t('LEGENDE.LESEN')) ?></span>
</div>

<h2><?= li_e(li_t('TEST.H')) ?></h2>
<p class="sm-hilfe"><?= li_t('TEST.HILFE') ?></p>
<?php
/* Drei Ausgaenge je Zeile: 1 Haken, 0 Kreuz, null Hinweis ("nicht
   feststellbar" oder "zur Kenntnis") - ein Kreuz, das nichts bedeutet, ist
   schlimmer als keine Pruefung. */
$li_zeilen = array();

$li_r = li_reiterprobe($li_reiter, __FILE__);
$li_zeilen[] = array(li_t('TEST.REITER'), $li_r[0], $li_r[1]);

$li_r = li_formularprobe(__FILE__);
$li_zeilen[] = array(li_t('TEST.FORMULARE'), $li_r[0], $li_r[1]);

$li_z = li_config_zustand();
$li_zeilen[] = array(li_t('TEST.KONFIG_HEIL'),
    in_array($li_z, array('ok', 'neu'), true) ? 1 : ($li_z === 'zweitschrift' ? null : 0),
    li_t('TEST.KONFIG_' . strtoupper($li_z)));

$li_lage = li_cfg_lage();
if ($li_lage['fremd']) {
    $li_zeilen[] = array(li_t('TEST.KONFIG_VOLL'), null, sprintf(li_t('TEST.KONFIG_FREMD'), implode(', ', $li_lage['fremd'])));
} elseif ($li_lage['fehlend']) {
    $li_zeilen[] = array(li_t('TEST.KONFIG_VOLL'), 0, sprintf(li_t('TEST.KONFIG_FEHLT'), count($li_lage['fehlend']), $li_lage['anzahl'], implode(', ', $li_lage['fehlend'])));
} else {
    $li_zeilen[] = array(li_t('TEST.KONFIG_VOLL'), 1, sprintf(li_t('TEST.KONFIG_JA'), $li_lage['anzahl'], $li_lage['anzahl']));
}

$li_zeilen[] = array(li_t('TEST.ZWEITSCHRIFT'),
    ($li_p['zweitschrift'] !== '' && is_file($li_p['zweitschrift'])) ? 1 : ($li_p['zweitschrift'] === '' ? null : 0),
    $li_p['zweitschrift'] === '' ? li_t('TEST.NICHT_INSTALLIERT') : $li_p['zweitschrift']);

$li_zeilen[] = array(li_t('TEST.SKRIPT'),
    (is_file($li_p['skript']) && is_executable($li_p['skript'])) ? 1 : 0, $li_p['skript']);

foreach (array('rsvg-convert', 'wget') as $li_prog) {
    $li_w = li_programm($li_prog);
    $li_zeilen[] = array(sprintf(li_t('TEST.PROGRAMM'), $li_prog), $li_w !== '' ? 1 : 0,
        $li_w !== '' ? $li_w : li_t('TEST.NICHT_GEFUNDEN'));
}
$li_w = li_programm('7z');
if ($li_w === '') {
    $li_w = li_programm('zip');
}
$li_zeilen[] = array(li_t('TEST.PACKER'), $li_w !== '' ? 1 : 0, $li_w !== '' ? $li_w : li_t('TEST.NICHT_GEFUNDEN'));

$li_zeilen[] = array(li_t('TEST.DATEN_SCHREIBBAR'), is_dir($li_p['data']) && is_writable($li_p['data']) ? 1 : 0, $li_p['data']);

$li_verweis = __DIR__ . '/files';
if (is_link($li_verweis)) {
    $li_ziel = (string) @readlink($li_verweis);
    $li_zeilen[] = array(li_t('TEST.VERWEIS'), rtrim($li_ziel, '/') === rtrim($li_p['data'], '/') ? 1 : 0, 'files -> ' . $li_ziel);
} else {
    $li_zeilen[] = array(li_t('TEST.VERWEIS'), $li_p['home'] === '' ? null : 0,
        $li_p['home'] === '' ? li_t('TEST.NICHT_INSTALLIERT') : li_t('TEST.KEIN_VERWEIS'));
}

$li_zeilen[] = array(li_t('TEST.LISTE'), $li_listenzahl > 0 ? 1 : 0,
    sprintf(li_t('TEST.LISTE_ZAHL'), $li_listenzahl));

if ($li_letzter === null) {
    $li_zeilen[] = array(li_t('TEST.LETZTER'), null, li_t('TEST.LETZTER_KEINER'));
} else {
    $li_zeilen[] = array(li_t('TEST.LETZTER'), (int) $li_letzter['rc'] === 0 ? 1 : 0,
        sprintf(li_t('TEST.LETZTER_WIE'), date('Y-m-d H:i', (int) $li_letzter['ende']), (int) $li_letzter['rc']));
    $li_fehl = $li_letzter['fehlend'];
    if ((int) $li_letzter['soll'] === 0) {
        $li_zeilen[] = array(li_t('TEST.VOLLZAHL'), null, li_t('TEST.VOLLZAHL_OHNE_LISTE'));
    } elseif ((int) $li_letzter['vorhanden'] === 0) {
        $li_zeilen[] = array(li_t('TEST.VOLLZAHL'), 0, sprintf(li_t('TEST.VOLLZAHL_WIE'), 0, (int) $li_letzter['soll']));
    } elseif ($li_fehl) {
        $li_zeilen[] = array(li_t('TEST.VOLLZAHL'), null,
            sprintf(li_t('TEST.VOLLZAHL_WIE'), (int) $li_letzter['vorhanden'], (int) $li_letzter['soll'])
            . ' ' . sprintf(li_t('TEST.VOLLZAHL_FEHLEN'), implode(', ', array_map('strval', $li_fehl))));
    } else {
        $li_zeilen[] = array(li_t('TEST.VOLLZAHL'), 1, sprintf(li_t('TEST.VOLLZAHL_WIE'), (int) $li_letzter['vorhanden'], (int) $li_letzter['soll']));
    }
}

$li_n = li_reste();
$li_zeilen[] = array(li_t('TEST.RESTE'), $li_n === 0 ? 1 : 0, sprintf(li_t('TEST.RESTE_ZAHL'), $li_n));

$li_zip = $li_archive[0];
if (!$li_zip['da']) {
    $li_zeilen[] = array(li_t('TEST.ZIP'), 0, li_t('EINST.NOCH_NICHT'));
} elseif (class_exists('ZipArchive')) {
    $li_za = new ZipArchive();
    $li_ok = $li_za->open($li_p['data'] . '/' . 'loxone_icons/loxone_icons.zip') === true;
    $li_zeilen[] = array(li_t('TEST.ZIP'), $li_ok ? 1 : 0,
        $li_ok ? sprintf(li_t('TEST.ZIP_EINTRAEGE'), $li_za->numFiles) : li_t('TEST.ZIP_UNLESBAR'));
    if ($li_ok) {
        $li_za->close();
    }
} else {
    $li_zeilen[] = array(li_t('TEST.ZIP'), null, li_t('TEST.ZIP_OHNE_ZIPARCHIVE'));
}

$li_zeilen[] = array(li_t('TEST.LAEUFT'), null,
    $li_laeuft ? sprintf(li_t('TEST.LAEUFT_JA'), $li_pid) : li_t('TEST.LAEUFT_NEIN'));

/* Die Netzprobe laeuft nur, wenn der Reiter Test serverseitig offen ist. */
if ($li_tab === 'tab-test') {
    $li_r = li_netzprobe($li_probe_url);
    $li_zeilen[] = array(li_t('TEST.NETZ'), $li_r[0], $li_r[1]);
} else {
    $li_zeilen[] = array(li_t('TEST.NETZ'), null, li_t('TEST.NETZ_NUR_IM_REITER'));
}
?>
<table class="sm-tbl">
	<tr><th><?= li_e(li_t('TEST.SP_FRAGE')) ?></th><th><?= li_e(li_t('TEST.SP_ERGEBNIS')) ?></th><th><?= li_e(li_t('TEST.SP_GEMESSEN')) ?></th></tr>
<?php foreach ($li_zeilen as $li_zl) { ?>
	<tr>
		<td><?= li_e($li_zl[0]) ?></td>
		<td><?php if ($li_zl[1] === 1) { ?><span class="sm-an">&#10004; <?= li_e(li_t('TEST.JA')) ?></span><?php } elseif ($li_zl[1] === 0) { ?><span class="sm-aus">&#10008; <?= li_e(li_t('TEST.NEIN')) ?></span><?php } else { ?><span class="sm-grau"><?= li_e(li_t('TEST.HINWEIS')) ?></span><?php } ?></td>
		<td class="sm-mono"><?= li_e($li_zl[2]) ?></td>
	</tr>
<?php } ?>
</table>

<div class="sm-knopfreihe">
	<a data-role="none" class="sm-btn sm-b-lesen" href="index.php?form=test"><?= li_e(li_t('TEST.K_NEU')) ?></a>
</div>
</div>

<!-- ================= Logdateien ================= -->
<div class="sm-seite<?= $li_tab === 'tab-log' ? ' sm-active' : '' ?>" id="tab-log">
<h2><?= li_e(li_t('LOG.H')) ?></h2>
<div class="sm-hinweis"><?= li_t('LOG.RAMDISK') ?></div>
<?php
if ($li_rahmen && method_exists('LBWeb', 'loglist_html')) {
    echo LBWeb::loglist_html();
} else {
    echo '<p class="sm-grau">' . li_e(li_t('LOG.OHNE_RAHMEN')) . '</p>';
}
?>
</div>

</div>

<script>
(function () {
	var reiter = document.querySelectorAll('.sm-tab');
	function zeige(id) {
		reiter.forEach(function (r) { r.classList.toggle('sm-active', r.dataset.ziel === id); });
		document.querySelectorAll('.sm-seite').forEach(function (s) { s.classList.toggle('sm-active', s.id === id); });
		document.querySelectorAll('input[name="activetab"]').forEach(function (f) { f.value = id; });
		if (history.replaceState) { history.replaceState(null, '', 'index.php?form=' + id.replace('tab-', '')); }
	}
	reiter.forEach(function (r) {
		r.addEventListener('click', function (e) { e.preventDefault(); zeige(r.dataset.ziel); });
	});
	zeige(<?= json_encode($li_tab) ?>);

	/* Fortschritt. Der Download laeuft abgekoppelt und dauert Minuten. Nach dem
	   Start kann die Sperrdatei noch fehlen - "laeuft nicht" gilt deshalb erst
	   als "fertig", wenn der Lauf einmal gesehen wurde oder 15 Sekunden vorbei
	   sind. */
	var aktiv = <?= ($li_laeuft || $li_gestartet) ? 'true' : 'false' ?>;
	var gesehen = <?= $li_laeuft ? 'true' : 'false' ?>;
	var versuche = 0;
	var t_laeuft = <?= json_encode(li_t('EINST.LAEUFT')) ?>;
	var t_ruht = <?= json_encode(li_t('EINST.RUHT')) ?>;
	function frage() {
		versuche++;
		var x = new XMLHttpRequest();
		x.open('GET', 'index.php?ajax=status', true);
		x.onload = function () {
			var d = null;
			try { d = JSON.parse(x.responseText); } catch (e) { d = null; }
			if (!d) { setTimeout(frage, 3000); return; }
			document.getElementById('li-svg').textContent = d.svg;
			document.getElementById('li-lauf').textContent = d.laeuft ? t_laeuft : t_ruht;
			if (d.laeuft) { gesehen = true; setTimeout(frage, 3000); return; }
			if (gesehen || versuche >= 5) { location.href = 'index.php?form=settings'; return; }
			setTimeout(frage, 3000);
		};
		x.onerror = function () { setTimeout(frage, 5000); };
		x.send();
	}
	if (aktiv) { setTimeout(frage, 3000); }
})();
</script>
<?php
if ($li_rahmen) {
    LBWeb::lbfooter();
}
