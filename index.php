<?php
// A placer à la racine du répertoire que vous souhaitez exposer

declare(strict_types=1);

// ----------------------------
// Sécurité et configuration
// ----------------------------
$BASE = realpath(__DIR__);
if ($BASE === false) {
    http_response_code(500);
    exit('Chemin de base invalide');
}
$BASE = rtrim($BASE, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function join_paths(string ...$parts): string {
    $path = implode(DIRECTORY_SEPARATOR, $parts);
    // Normalisation doubles séparateurs
    return preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR, '#') . '{2,}#', DIRECTORY_SEPARATOR, $path) ?? $path;
}
function bytesToHuman(int $bytes): string {
    $units = ['octets', 'Ko', 'Mo', 'Go', 'To', 'Po'];
    $i = 0; $v = (float)$bytes;
    while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
    $num = $i === 0 ? (string)(int)$v : number_format($v, 2, ',', ' ');
    return $num . ' ' . $units[$i];
}
function toRel(string $abs, string $base): string {
    $rel = ltrim(substr($abs, strlen($base)), DIRECTORY_SEPARATOR);
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    return $rel;
}
function buildLink(string $rel): string {
    // Pour la navigation de dossiers: génère ?p=chemin/encodé
    $rel = trim($rel, '/');
    if ($rel === '') return '?p=';
    $segments = array_map('rawurlencode', explode('/', $rel));
    return '?p=' . implode('/', $segments);
}
function relToUrlPath(string $rel): string {
    // Pour les liens de fichiers: génère un chemin relatif encodé ex: sous%20dossier/fichier.txt
    $rel = trim($rel, '/');
    if ($rel === '') return '';
    $segments = array_map('rawurlencode', explode('/', $rel));
    return implode('/', $segments);
}

// ----------------------------
// Extensions et noms de fichiers à masquer
// ----------------------------
$blockedExt = ['ini', 'php', 'ico'];
$blockedNames = ['.htaccess'];

// ----------------------------
// Détermination du dossier courant
// ----------------------------
$req = isset($_GET['p']) ? (string)$_GET['p'] : '';
$req = str_replace('\\', '/', $req);
$req = trim($req, '/');
$target = realpath(join_paths($BASE, str_replace('/', DIRECTORY_SEPARATOR, $req)));
if ($target === false || strncmp($target, $BASE, strlen($BASE)) !== 0 || !is_dir($target)) {
    $target = $BASE;
    $req = '';
}

$listing = @scandir($target) ?: [];
$dirs = [];
$files = [];
foreach ($listing as $name) {
    if ($name === '.' || $name === '..') continue;
    $abs = join_paths($target, $name);
    if (!is_readable($abs)) continue;

    if (is_dir($abs)) {
        $dirs[] = $name;
    } else {
        $lcname = strtolower($name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($lcname, $blockedNames, true)) continue;
        if (in_array($ext, $blockedExt, true)) continue;
        $files[] = $name;
    }
}

natcasesort($dirs); $dirs = array_values($dirs);
natcasesort($files); $files = array_values($files);

// Fil d'Ariane
$breadcrumbs = [];
$breadcrumbs[] = ['🏠', ''];
$parts = $req === '' ? [] : explode('/', $req);
$acc = [];
foreach ($parts as $part) {
    $acc[] = $part;
    $breadcrumbs[] = [$part, implode('/', $acc)];
}

// Lien dossier parent
$parentRel = '';
if ($req !== '') {
    $tmp = $parts; array_pop($tmp);
    $parentRel = implode('/', $tmp);
}

// Compte des éléments
$totalDirs = count($dirs);
$totalFiles = count($files);

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Explorateur de fichier</title>
<style>
    :root { --bg:#0b1020; --panel:#12172b; --panel2:#161c34; --text:#e6e8ef; --muted:#9aa3b2; --accent:#5b8cff; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; background: var(--bg); color: var(--text); display: flex; flex-direction: column; min-height: 100vh; }
    header { padding: 16px 24px; background: var(--panel); position: sticky; top: 0; z-index: 10; border-bottom: 1px solid #222941; }
    h1 { margin: 0; font-size: 18px; font-weight: 600; }
    .crumbs { margin-top: 6px; font-size: 14px; color: var(--muted); display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .crumbs a { color: #fff; text-decoration: none; padding: 2px 6px; border-radius: 6px; background: var(--panel2); transition: color 0.2s ease; }
    .crumbs a:hover { color: #ccc; }
    .container { flex: 1; padding: 16px 24px; }
    .toolbar { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; background: var(--panel); border: 1px solid #222941; border-radius: 10px; text-decoration: none; color: #fff; font-size: 14px; transition: color 0.2s ease; }
    .btn:hover { border-color: var(--accent); color: #ccc; }
    table { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid #222941; border-radius: 14px; overflow: hidden; }
    thead th { text-align: left; font-weight: 600; font-size: 14px; padding: 12px 14px; background: #0f1529; border-bottom: 1px solid #222941; }
    tbody td { padding: 10px 14px; font-size: 14px; border-top: 1px solid #1b2340; }
    tbody tr:hover { background: #121a33; }
    .name { display: flex; align-items: center; gap: 10px; }
    .icon { width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; background: var(--panel2); font-size: 13px; }
    .name a { color: #fff; text-decoration: none; transition: color 0.2s ease; }
    .name a:hover { color: #ccc; }
    .muted { color: var(--muted); }
    .right { text-align: right; }
    footer { padding: 14px; text-align: center; font-size: 13px; color: var(--muted); border-top: 1px solid #222941; background: var(--panel); }
    footer a { color: #fff; text-decoration: none; }
    footer a:hover { color: #ccc; }
    @media (max-width: 720px) {
        .col-size, .col-type { display: none; }
    }
</style>
</head>
<body>
<header>
    <h1>Explorateur de fichier</h1>
<nav class="crumbs">
    <?php foreach ($breadcrumbs as [$label, $rel]): ?>
        <?php if ($rel === ''): ?>
            <a href="/">🏠</a>
        <?php else: ?>
            <a href="<?= h(buildLink($rel)) ?>"><?= h($label) ?></a>
        <?php endif; ?>
        <span class="muted">/</span>
    <?php endforeach; ?>
    <span class="muted"><?= $totalDirs ?> dossier(s), <?= $totalFiles ?> fichier(s)</span>
</nav>
</header>
<div class="container">
    <div class="toolbar">
        <?php if ($req !== ''): ?>
            <a class="btn" href="<?= h(buildLink($parentRel)) ?>">⬅️</a>
        <?php else: ?>
            <span class="btn muted">⬅️</span>
        <?php endif; ?>
        <span class="muted">Dossier courant: <?= h('/' . ($req === '' ? '' : $req . '/')) ?></span>
    </div>
    <table role="grid" aria-label="Liste des fichiers et dossiers">
        <thead>
            <tr>
                <th>Nom</th>
                <th class="col-type">Type</th>
                <th class="col-size right">Taille</th>
                <th>Modifié</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dirs as $d):
                $abs = join_paths($target, $d);
                $rel = trim(($req === '' ? '' : $req . '/') . $d, '/');
                $mtime = @filemtime($abs) ?: time();
            ?>
            <tr>
                <td class="name">
                    <span class="icon">📁</span>
                    <a href="<?= h(buildLink($rel)) ?>"><?= h($d) ?></a>
                </td>
                <td class="col-type">Dossier</td>
                <td class="col-size right">—</td>
                <td><span class="muted"><?= date('Y-m-d H:i', $mtime) ?></span></td>
            </tr>
            <?php endforeach; ?>

            <?php foreach ($files as $f):
                $abs = join_paths($target, $f);
                $rel = trim(($req === '' ? '' : $req . '/') . $f, '/');
                $mtime = @filemtime($abs) ?: time();
                $size = @filesize($abs) ?: 0;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            ?>
            <tr>
                <td class="name">
                    <span class="icon">📄</span>
                    <a href="<?= h(relToUrlPath($rel)) ?>" target="_blank" rel="noopener"><?= h($f) ?></a>
                </td>
                <td class="col-type"><?= $ext !== '' ? h(strtoupper($ext)) : 'Fichier' ?></td>
                <td class="col-size right"><?= h(bytesToHuman((int)$size)) ?></td>
                <td><span class="muted"><?= date('Y-m-d H:i', $mtime) ?></span></td>
            </tr>
            <?php endforeach; ?>

            <?php if ($totalDirs === 0 && $totalFiles === 0): ?>
            <tr><td colspan="4" class="muted">Aucun élément</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<footer>
    <a href="https://jiti.me/">Jiti Expert Corp.</a>
</footer>
</body>
</html>

