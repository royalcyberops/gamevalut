<?php
// tools/fix_images.php — One-click repair: restore game cover images.
// Open http://localhost/steam/tools/fix_images.php in the browser.
// Add ?dry=1 to preview what would change without writing anything.
//
// WHY: covers ship inside the repo (assets/img/*.jpg), but pages only render
// a cover when `games.image` points at an existing whitelisted file (see
// cover_html() in index.php). Databases created before the covers were added
// — or imported by hand — have image = NULL / stale paths, so the shop falls
// back to gradient letter tiles. This tool fixes exactly that.
//
// WHAT: scans assets/img/, then repairs every game whose image value is
// missing or broken — first via the seed list in database.sql, then by
// matching the title against the actual file names. Only broken rows are
// updated; working values are never overwritten. Idempotent: safe to re-run;
// only touches the games.image column.

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'gamevault_db';

// Same whitelist the site itself enforces (index.php cover_html, admin.php).
$IMG_PATTERN = '#^assets/img/[a-z0-9-]+\.(jpg|jpeg|png|webp|svg)$#i';

// ?dry=1 — report what would change, write nothing.
$dry = isset($_GET['dry']) && $_GET['dry'] === '1';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fix Cover Images | GameVault</title>
<style>
  body { font-family: "Segoe UI", system-ui, sans-serif; background: #0b0d14; color: #e8ecf5; padding: 40px 20px; }
  main { max-width: 760px; margin: 0 auto; background: #121420; border: 1px solid #00f3ff55; border-radius: 14px; padding: 32px; }
  h1 { color: #00f3ff; } h2 { color: #00f3ff; font-size: 1rem; margin: 26px 0 8px; }
  a { color: #00f3ff; }
  .ok { color: #00ff88; } .err { color: #ff5e5e; } .skip { color: #8a93a8; } .warn { color: #ffc857; }
  .btn { display: inline-block; margin-top: 12px; padding: 11px 22px; border-radius: 8px; background: #00ff88; color: #0b0d14; font-weight: 800; text-decoration: none; }
  table { width: 100%; border-collapse: collapse; font-size: .85rem; }
  th, td { padding: 7px 9px; border-bottom: 1px solid #ffffff1a; text-align: left; vertical-align: top; }
  th { color: #00f3ff; font-size: .78rem; text-transform: uppercase; letter-spacing: .4px; }
</style>
</head>
<body>
<main>
<h1>Fix Cover Images</h1>
<?php
function img_out($msg, $cls = '') {
    echo '<p class="' . $cls . '">' . htmlspecialchars($msg) . '</p>';
}

/** "Grand Theft Auto V" -> "grand-theft-auto-v" (a file-name slug). */
function title_slug($s) {
    $s = strtolower((string)$s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/**
 * title => image map from the seed INSERTs in database.sql. Using the seed
 * list keeps repairs in sync with canonical data — it also finds covers with
 * abbreviated names like gta-v.jpg / bg3.jpg that no title-slug rule could
 * derive.
 */
function seed_image_map($sqlFile) {
    $map = [];
    if (!is_readable($sqlFile)) return $map;
    $sql = file_get_contents($sqlFile);
    // Only `games` INSERT blocks; [^;]* stays within one statement, the same
    // no-semicolons-inside-strings assumption tools/setup.php already makes.
    if (!preg_match_all('/INSERT\s+INTO\s+`?games`?\b[^;]*;/i', $sql, $blocks)) return $map;
    $tuple = "/\(\s*'((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*,\s*[0-9.]+\s*,\s*\d+\s*,\s*'((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*,\s*'((?:[^']|'')*)'\s*\)/";
    foreach ($blocks[0] as $block) {
        if (!preg_match_all($tuple, $block, $rows, PREG_SET_ORDER)) continue;
        foreach ($rows as $r) {
            $title = strtolower(trim(str_replace("''", "'", $r[1])));
            $img   = trim(str_replace("''", "'", $r[5])); // group 5 = image column
            if ($title !== '' && $img !== '') $map[$title] = $img;
        }
    }
    return $map;
}

/**
 * Does a stored value point at a real file? Returns the on-disk file name
 * (real casing) or '' when missing / not usable / not whitelisted.
 */
function existing_file($value, $filesByLower) {
    $v = str_replace('\\', '/', trim((string)$value));
    if (!preg_match('#^assets/img/([a-z0-9-]+\.(jpg|jpeg|png|webp|svg))$#i', $v, $m)) return '';
    return $filesByLower[strtolower($m[1])] ?? '';
}

/**
 * Fallback for games NOT in the seed list (e.g. added via admin.php): match
 * the title against actual file names. Tiers: exact slug, then file slug is
 * a prefix of the title slug, then every file token appears in the title.
 * A tier is used only when exactly one file matches it — the tool never
 * guesses between covers.
 */
function match_file_by_title($title, $stems) {
    $slug   = title_slug($title);
    if ($slug === '') return '';
    $tokens = explode('-', $slug);
    $exact = $prefix = $subset = [];
    foreach ($stems as $stem => $names) {
        if (count($names) !== 1) continue; // same stem twice on disk => ambiguous
        $name = $names[0];
        if ($stem === $slug) { $exact[] = $name; continue; }
        if (strlen($stem) >= 3 && str_starts_with($slug, $stem)) { $prefix[] = $name; continue; }
        if (array_diff(explode('-', $stem), $tokens) === []) $subset[] = $name;
    }
    foreach ([$exact, $prefix, $subset] as $tier) {
        if (count($tier) === 1) return $tier[0];
        if (count($tier) > 1) return ''; // several candidates — leave it to admin.php
    }
    return '';
}

try {
    // 1. Index the cover files that shipped with the repo.
    $imgAbs       = __DIR__ . '/../assets/img';
    $filesByLower = []; // lower-case name => real on-disk name
    $stems        = []; // lower-case stem => [real on-disk name, ...]
    $ignored      = 0;
    if (is_dir($imgAbs)) {
        foreach (scandir($imgAbs) as $fn) {
            if (!is_file($imgAbs . '/' . $fn)) continue;
            if (!preg_match($IMG_PATTERN, 'assets/img/' . $fn)) { $ignored++; continue; }
            $filesByLower[strtolower($fn)] = $fn;
            $stems[strtolower(pathinfo($fn, PATHINFO_FILENAME))][] = $fn;
        }
    }
    if (!$filesByLower) {
        throw new RuntimeException('No usable cover files in assets/img/ — pull the latest code first, then re-run this tool.');
    }
    img_out('Found ' . count($filesByLower) . ' cover file(s) in assets/img/'
        . ($ignored ? ' — ' . $ignored . ' file(s) ignored (names must use only lowercase letters, numbers and dashes).' : '.'));

    // 2. Load the canonical title => image list from the seed file.
    $seedMap = seed_image_map(__DIR__ . '/../database.sql');
    if ($seedMap) {
        img_out('Seed cover list loaded from database.sql: ' . count($seedMap) . ' titles.', 'skip');
    } else {
        img_out('database.sql not readable — falling back to file-name matching only.', 'warn');
    }

    // 3. Connect.
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    img_out('Connected to `' . $DB_NAME . '`.');

    // 3b. Very old databases may predate the image column itself.
    if (!$pdo->query("SHOW COLUMNS FROM `games` LIKE 'image'")->fetch()) {
        if ($dry) {
            throw new RuntimeException('games.image column is missing — run this tool again without ?dry=1 to add it and repair the covers.');
        }
        $pdo->exec("ALTER TABLE `games` ADD COLUMN `image` VARCHAR(255) NULL DEFAULT NULL COMMENT 'optional file in assets/img/' AFTER `description`");
        img_out('Added the missing games.image column.', 'ok');
    }

    // 4. Repair every game whose stored cover is missing or broken.
    $games = $pdo->query('SELECT game_id, title, image FROM games ORDER BY game_id')->fetchAll();
    if (!$games) {
        img_out('The games table is empty — nothing to repair. Run tools/setup.php first to install the shop.', 'warn');
        echo '<a class="btn" href="setup.php">Open setup</a>';
        echo '</main></body></html>';
        exit;
    }

    $update = $pdo->prepare('UPDATE games SET image = ? WHERE game_id = ?');
    $report = [];
    $fixed = $ok = $skipped = 0;

    foreach ($games as $g) {
        $raw  = trim((string)$g['image']);          // exactly what the DB stores
        $cur  = str_replace('\\', '/', $raw);       // slash-normalised for resolving
        $real = existing_file($cur, $filesByLower);

        // a) Already points at a real, whitelisted file.
        if ($real !== '') {
            $good = 'assets/img/' . $real;
            if ($raw === $good) {
                $ok++;
                $report[] = [$g['title'], $raw, 'skip', 'OK'];
            } else {
                // Right file, sloppy text (case / backslashes) — normalise it.
                if (!$dry) $update->execute([$good, $g['game_id']]);
                $fixed++;
                $report[] = [$g['title'], $cur, 'ok', ($dry ? 'WOULD FIX' : 'FIXED') . ' (path normalised) -> ' . $good];
            }
            continue;
        }

        // b) Broken value — try the seed list first, then file-name matching.
        $key      = strtolower(trim((string)$g['title']));
        $resolved = '';
        if (isset($seedMap[$key])) $resolved = existing_file($seedMap[$key], $filesByLower);
        if ($resolved === '')      $resolved = match_file_by_title($g['title'], $stems);

        if ($resolved === '') {
            $skipped++;
            $hint = 'no matching cover — add assets/img/' . (title_slug($g['title']) ?: 'game') . '.jpg, then re-run';
            $report[] = [$g['title'], $raw === '' ? '(empty)' : $raw, 'warn', 'SKIPPED: ' . $hint];
            continue;
        }

        $good = 'assets/img/' . $resolved;
        if (!$dry) $update->execute([$good, $g['game_id']]);
        $fixed++;
        $report[] = [$g['title'], $raw === '' ? '(empty)' : $raw, 'ok', ($dry ? 'WOULD FIX' : 'FIXED') . ' -> ' . $good];
    }

    // 5. Summary + per-game report.
    img_out(count($games) . ' game(s) checked: ' . $ok . ' already fine, ' . $fixed
        . ($dry ? ' repairable' : ' repaired') . ($skipped ? ', ' . $skipped . ' skipped' : '') . '.',
        ($fixed || $skipped) ? ($skipped ? 'warn' : 'ok') : 'ok');

    echo '<h2>Report</h2><table><thead><tr><th>Game</th><th>Stored path before</th><th>Result</th></tr></thead><tbody>';
    foreach ($report as $r) {
        echo '<tr><td>' . htmlspecialchars($r[0]) . '</td><td>' . htmlspecialchars($r[1]) . '</td><td class="' . $r[2] . '">' . htmlspecialchars($r[3]) . '</td></tr>';
    }
    echo '</tbody></table>';

    echo '<p class="ok"><strong>Done!</strong> ' . ($fixed ? 'Reload the shop — covers should be back.' : 'Everything was already in order.') . '</p>';
    echo '<a class="btn" href="../index.php">Open GameVault Shop</a>';
    echo '<p class="skip" style="font-size:.8rem">Safe to re-run. Add <code>?dry=1</code> to the URL to preview changes without writing.</p>';
} catch (RuntimeException $e) {
    echo '<p class="err"><strong>Stopped.</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
} catch (Throwable $e) {
    error_log('GameVault fix_images failed: ' . $e->getMessage());
    echo '<p class="err"><strong>Could not repair covers.</strong> Make sure MySQL is running in XAMPP and the database is installed (run tools/setup.php once). Check the PHP error log for details.</p>';
}
?>
</main>
</body>
</html>
