<?php
// tools/migrate_shop.php — One-click upgrade: booking system -> GameVault Shop.
// Open http://localhost/steam/tools/migrate_shop.php in the browser.
// Idempotent: safe to re-run. Adds users.is_admin, orders + order_items
// tables, and seeds the admin account (admin / Admin@123).

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'gamevault_db';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Shop Migration | GameVault</title>
<style>
  body { font-family: "Segoe UI", system-ui, sans-serif; background: #0b0d14; color: #e8ecf5; padding: 40px 20px; }
  main { max-width: 640px; margin: 0 auto; background: #121420; border: 1px solid #00f3ff55; border-radius: 14px; padding: 32px; }
  h1 { color: #00f3ff; } a { color: #00f3ff; }
  .ok { color: #00ff88; } .err { color: #ff5e5e; } .skip { color: #8a93a8; }
  .btn { display: inline-block; margin-top: 12px; padding: 11px 22px; border-radius: 8px; background: #00ff88; color: #0b0d14; font-weight: 800; text-decoration: none; }
</style>
</head>
<body>
<main>
<h1>GameVault Shop Migration</h1>
<?php
function shop_out($msg, $cls = '') {
    echo '<p class="' . $cls . '">' . htmlspecialchars($msg) . '</p>';
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    shop_out('Connected to `' . $DB_NAME . '`.');

    // 1. users.is_admin flag (skip if the column already exists).
    $col = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'is_admin'")->fetch();
    if ($col) {
        shop_out('users.is_admin already exists — skipped.', 'skip');
    } else {
        $pdo->exec('ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`');
        shop_out('Added users.is_admin.', 'ok');
    }

    // 2. orders table (one row per checkout: contact + delivery slot + total).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `orders` (
          `order_id`      INT AUTO_INCREMENT PRIMARY KEY,
          `user_id`       INT NOT NULL,
          `contact_name`  VARCHAR(100) NOT NULL,
          `contact_phone` VARCHAR(10)  NOT NULL,
          `address`       TEXT NOT NULL,
          `delivery_date` DATE NOT NULL COMMENT 'preferred delivery date',
          `delivery_time` TIME NOT NULL COMMENT 'preferred delivery time',
          `notes`         TEXT NULL,
          `payment_method` VARCHAR(20) NOT NULL DEFAULT 'COD',
          `order_status`  ENUM('Pending','Confirmed','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
          `total_amount`  DECIMAL(10,2) NOT NULL,
          `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
            REFERENCES `users` (`user_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    shop_out('orders table ready.', 'ok');

    // 3. order_items table (one row per game in an order).
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `order_items` (
          `item_id`    INT AUTO_INCREMENT PRIMARY KEY,
          `order_id`   INT NOT NULL,
          `game_id`    INT NOT NULL,
          `quantity`   INT NOT NULL DEFAULT 1,
          `unit_price` DECIMAL(10,2) NOT NULL COMMENT 'discounted unit price at purchase time',
          CONSTRAINT `fk_items_order` FOREIGN KEY (`order_id`)
            REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
          CONSTRAINT `fk_items_game` FOREIGN KEY (`game_id`)
            REFERENCES `games` (`game_id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    shop_out('order_items table ready.', 'ok');

    // 4. Seed admin account (admin / Admin@123). Never overwrite an existing admin row.
    $chk = $pdo->prepare('SELECT user_id FROM `users` WHERE `username` = ? OR `email` = ? LIMIT 1');
    $chk->execute(['admin', 'admin@gamevault.local']);
    if ($chk->fetch()) {
        $pdo->prepare("UPDATE `users` SET `is_admin` = 1 WHERE `username` = ? OR `email` = ?")
            ->execute(['admin', 'admin@gamevault.local']);
        shop_out('Admin account already exists — ensured is_admin = 1.', 'skip');
    } else {
        $hash = password_hash('Admin@123', PASSWORD_DEFAULT);
        $ins = $pdo->prepare(
            'INSERT INTO `users` (full_name, email, mobile, username, password, is_admin)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $ins->execute(['Store Admin', 'admin@gamevault.local', '9876543210', 'admin', $hash]);
        shop_out('Admin account created: username `admin` / password `Admin@123`.', 'ok');
    }

    echo '<p class="ok"><strong>Done!</strong> Shop tables ready.</p>';
    echo '<a class="btn" href="../index.php">Open GameVault Shop</a>';
} catch (Throwable $e) {
    error_log('GameVault shop migration failed: ' . $e->getMessage());
    echo '<p class="err"><strong>Migration failed.</strong> Make sure MySQL is running and database.sql was imported first (run tools/setup.php). Check the PHP error log for details.</p>';
}
?>
</main>
</body>
</html>
