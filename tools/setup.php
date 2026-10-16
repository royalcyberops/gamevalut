<?php
// tools/setup.php — One-click GameVault database installer.
// Open http://localhost/steam/tools/setup.php in the browser.
// It creates `gamevault_db`, builds all tables and seeds the 22 games.
// Safe to re-run: if data already exists it stops (unless ?force=1 wipes + rebuilds).

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'gamevault_db';

$force = isset($_GET['force']) && $_GET['force'] === '1';
$log = [];

function out($msg) {
    echo '<p>' . htmlspecialchars($msg) . '</p>';
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Setup | GameVault</title>
<style>
  body { font-family: "Segoe UI", system-ui, sans-serif; background: #0b0d14; color: #e8ecf5; padding: 40px 20px; }
  main { max-width: 640px; margin: 0 auto; background: #121420; border: 1px solid #00f3ff55; border-radius: 14px; padding: 32px; }
  h1 { color: #00f3ff; } a { color: #00f3ff; }
  .ok { color: #00ff88; } .err { color: #ff5e5e; }
  .btn { display: inline-block; margin-top: 12px; padding: 11px 22px; border-radius: 8px; background: #00ff88; color: #0b0d14; font-weight: 800; text-decoration: none; }
</style>
</head>
<body>
<main>
<h1>GameVault Setup</h1>
<?php
try {
    // 1. Connect to the MySQL server itself (no database selected yet).
    $pdo = new PDO(
        "mysql:host=$DB_HOST;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    out('Connected to MySQL.');

    // 2. Fresh rebuild requested? Drop everything first.
    if ($force) {
        $pdo->exec("DROP DATABASE IF EXISTS `$DB_NAME`");
        out('Old database dropped (?force=1).');
    }

    // 3. Already installed? Stop unless forced (re-running the seed INSERTs would duplicate games).
    try {
        $chk = $pdo->query("SELECT COUNT(*) AS c FROM `$DB_NAME`.`games`");
        $count = (int) $chk->fetch(PDO::FETCH_ASSOC)['c'];
        if ($count > 0 && !$force) {
            echo '<p class="ok"><strong>Already installed:</strong> ' . $count . ' games found. Nothing changed.</p>';
            echo '<a class="btn" href="../index.php">Open GameVault</a>';
            echo '<p style="margin-top:14px;font-size:.85rem">To wipe and rebuild from scratch: <a href="?force=1">setup.php?force=1</a></p>';
            echo '</main></body></html>';
            exit;
        }
    } catch (PDOException $e) {
        // games table missing = fresh install, continue below.
    }

    // 4. Run database.sql statement by statement.
    $sqlFile = __DIR__ . '/../database.sql';
    if (!is_readable($sqlFile)) {
        throw new RuntimeException('database.sql not found next to the project root.');
    }
    $sql = file_get_contents($sqlFile);
    // Strip full-line comments (-- ...) and (# ...) so splitting on ";" is safe.
    $lines = explode("\n", $sql);
    $clean = [];
    foreach ($lines as $ln) {
        $t = ltrim($ln);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) continue;
        $clean[] = $ln;
    }
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $clean))));

    $ran = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $ran++;
    }
    out("Executed $ran SQL statements from database.sql.");

    // 4b. Ensure the staff account exists (fresh database.sql has tables but no users).
    // Never overwrite: only INSERT when neither the username nor email is taken.
    $adm_chk = $pdo->prepare("SELECT user_id FROM `$DB_NAME`.`users` WHERE `username` = ? OR `email` = ? LIMIT 1");
    $adm_chk->execute(['admin', 'admin@gamevault.local']);
    if ($adm_chk->fetch()) {
        $pdo->prepare("UPDATE `$DB_NAME`.`users` SET `is_admin` = 1 WHERE `username` = ? OR `email` = ?")
            ->execute(['admin', 'admin@gamevault.local']);
        out('Admin account already exists — ensured is_admin = 1.');
    } else {
        $adm_ins = $pdo->prepare(
            "INSERT INTO `$DB_NAME`.`users` (full_name, email, mobile, username, password, is_admin)
             VALUES (?, ?, ?, ?, ?, 1)"
        );
        $adm_ins->execute([
            'Store Admin', 'admin@gamevault.local', '9876543210', 'admin',
            password_hash('Admin@123', PASSWORD_DEFAULT),
        ]);
        out('Admin account created: username `admin` / password `Admin@123`.');
    }

    // 5. Verify.
    $games = (int) $pdo->query("SELECT COUNT(*) AS c FROM `$DB_NAME`.`games`")->fetch(PDO::FETCH_ASSOC)['c'];
    $cats  = (int) $pdo->query("SELECT COUNT(*) AS c FROM `$DB_NAME`.`categories`")->fetch(PDO::FETCH_ASSOC)['c'];
    echo '<p class="ok"><strong>Done!</strong> Database `' . htmlspecialchars($DB_NAME) . '` ready: ' . $games . ' games, ' . $cats . ' categories.</p>';
    echo '<p style="font-size:.85rem">Staff login: <code>admin</code> / <code>Admin@123</code> (change it after first login).</p>';
    echo '<a class="btn" href="../index.php">Open GameVault</a>';
} catch (Throwable $e) {
    // Log the real error server-side; show a friendly hint in the browser.
    error_log('GameVault setup failed: ' . $e->getMessage());
    echo '<p class="err"><strong>Setup failed.</strong> Make sure MySQL is running in XAMPP (port 3306) and that the MySQL user `root` needs no password. Check the PHP error log for details.</p>';
    echo '<p style="font-size:.85rem">Tip: default XAMPP credentials are user <code>root</code> with an empty password. If you set a password, update it in <code>tools/setup.php</code> and <code>includes/db.php</code>.</p>';
}
?>
</main>
</body>
</html>
