<?php
// admin.php — GameVault store admin (admin-only page).
// Order management (Pending -> Confirmed -> Delivered, or Cancelled),
// catalogue overview, and add-game form. Guarded by includes/admin.php.

require_once __DIR__ . '/includes/admin.php'; // admin-only guard (includes auth + db)
require_once __DIR__ . '/includes/csrf.php';

$page_title  = 'Store Admin';
$active_page = 'admin';

$message   = '';
$msg_class = '';

// Whitelisted order statuses (never trust the posted value directly).
$valid_statuses = ['Pending', 'Confirmed', 'Delivered', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message   = 'Security check failed. Please reload the page and try again.';
        $msg_class = 'msg-error';
    } else {
        $form = is_string($_POST['form'] ?? '') ? $_POST['form'] : '';

        if ($form === 'status') {
            // Update one order's status (any user, any transition — admin decides).
            $order_id   = (is_numeric($_POST['order_id'] ?? 0)) ? (int) $_POST['order_id'] : 0;
            $new_status = is_string($_POST['new_status'] ?? '') ? $_POST['new_status'] : '';
            if ($order_id <= 0 || !in_array($new_status, $valid_statuses, true)) {
                $message   = 'Invalid order or status.';
                $msg_class = 'msg-error';
            } else {
                try {
                    $stmt = $conn->prepare('UPDATE orders SET order_status = ? WHERE order_id = ?');
                    $stmt->execute([$new_status, $order_id]);
                    if ($stmt->rowCount() > 0) {
                        $message   = 'Order #' . $order_id . ' set to ' . $new_status . '.';
                        $msg_class = 'msg-success';
                    } else {
                        $message   = 'Order #' . $order_id . ' not found or already ' . $new_status . '.';
                        $msg_class = 'msg-error';
                    }
                } catch (PDOException $e) {
                    $message   = 'Could not update the order. Please try again.';
                    $msg_class = 'msg-error';
                }
            }
        } elseif ($form === 'add_game') {
            // Add a catalogue game (genre comes from the chosen category — no duplication).
            $title       = is_string($_POST['title'] ?? '') ? trim($_POST['title']) : '';
            $category_id = (is_numeric($_POST['category_id'] ?? 0)) ? (int) $_POST['category_id'] : 0;
            $platform    = is_string($_POST['platform'] ?? '') ? trim($_POST['platform']) : '';
            $price_raw   = is_string($_POST['price'] ?? '') ? trim($_POST['price']) : '';
            $disc_raw    = is_string($_POST['discount'] ?? '') ? trim($_POST['discount']) : '';
            $description = is_string($_POST['description'] ?? '') ? trim($_POST['description']) : '';
            $image       = is_string($_POST['image'] ?? '') ? trim($_POST['image']) : '';

            $price = is_numeric($price_raw) ? (float) $price_raw : -1;
            $disc  = is_numeric($disc_raw) ? (int) $disc_raw : -1;

            if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
                $message = 'Game title must be 2-150 characters.';
            } elseif ($category_id <= 0) {
                $message = 'Please choose a category.';
            } elseif ($platform === '' || mb_strlen($platform) > 100) {
                $message = 'Platform is required (max 100 chars).';
            } elseif ($price <= 0) {
                $message = 'Price must be greater than zero.';
            } elseif ($disc < 0 || $disc > 100) {
                $message = 'Discount must be 0-100.';
            } elseif ($image !== '' && !preg_match('#^assets/img/[a-z0-9-]+\.(jpg|jpeg|png|webp|svg)$#i', $image)) {
                $message = 'Image must be a local assets/img/ file.';
            } else {
                $message = '';
            }

            if ($message !== '') {
                $msg_class = 'msg-error';
            } else {
                try {
                    // Genre display copy comes from the category row (single source of truth).
                    $cat = $conn->prepare('SELECT name FROM categories WHERE category_id = ?');
                    $cat->execute([$category_id]);
                    $cat_row = $cat->fetch();
                    if (!$cat_row) {
                        $message   = 'Please choose a valid category.';
                        $msg_class = 'msg-error';
                    } else {
                        $ins = $conn->prepare(
                            'INSERT INTO games (title, genre, category_id, platform, price, discount_percent, description, image, release_date)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $ins->execute([
                            $title, $cat_row['name'], $category_id, $platform, $price, $disc,
                            ($description === '' ? null : $description),
                            ($image === '' ? null : $image),
                            date('Y-m-d'),
                        ]);
                        $message   = 'Game "' . $title . '" added to the catalogue.';
                        $msg_class = 'msg-success';
                    }
                } catch (PDOException $e) {
                    $message   = 'Could not add the game. Please try again.';
                    $msg_class = 'msg-error';
                }
            }
        }
    }
}

// ---- Stats ----
$stat_orders = 0;
$stat_revenue = 0.00;
$stat_pending = 0;
$stat_games = 0;
try {
    $stat_orders  = (int) $conn->query('SELECT COUNT(*) c FROM orders')->fetch()['c'];
    $stat_revenue = (float) $conn->query(
        "SELECT COALESCE(SUM(total_amount), 0) s FROM orders WHERE order_status != 'Cancelled'"
    )->fetch()['s'];
    $stat_pending = (int) $conn->query(
        "SELECT COUNT(*) c FROM orders WHERE order_status = 'Pending'"
    )->fetch()['c'];
    $stat_games   = (int) $conn->query('SELECT COUNT(*) c FROM games')->fetch()['c'];
} catch (PDOException $e) {
    // Stats stay zeroed; tables below show their own friendly errors.
}

// ---- All orders (newest first) with buyer + item summary ----
$orders = [];
try {
    $stmt = $conn->prepare(
        'SELECT o.*, u.username,
                (SELECT GROUP_CONCAT(CONCAT(g.title, " x", i.quantity) SEPARATOR ", ")
                 FROM order_items i JOIN games g ON g.game_id = i.game_id
                 WHERE i.order_id = o.order_id) AS items
         FROM orders o JOIN users u ON u.user_id = o.user_id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $orders[] = $row;
    }
} catch (PDOException $e) {
    $orders = [];
}

// ---- Categories for the add-game form ----
$categories = [];
try {
    $stmt = $conn->prepare('SELECT category_id, name FROM categories ORDER BY name ASC');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $categories[] = $row;
    }
} catch (PDOException $e) {
    $categories = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="section-title">Store Admin</h2>
<p class="muted">Orders, revenue and catalogue — staff only.</p>

<?php if ($message !== ''): ?>
  <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="stat-row reveal">
  <div class="stat-box warm">
    <span class="stat-icon">&#128722;</span>
    <p class="stat-num"><?php echo (int) $stat_orders; ?></p>
    <p class="stat-label">Total Orders</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#128176;</span>
    <p class="stat-num">&#8377;<?php echo number_format($stat_revenue, 2); ?></p>
    <p class="stat-label">Revenue (excl. cancelled)</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#9203;</span>
    <p class="stat-num"><?php echo (int) $stat_pending; ?></p>
    <p class="stat-label">Pending Orders</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#127918;</span>
    <p class="stat-num"><?php echo (int) $stat_games; ?></p>
    <p class="stat-label">Games in Catalogue</p>
  </div>
</div>

<h3 class="section-title reveal">&#128203; All Orders</h3>
<?php if (empty($orders)): ?>
  <div class="empty-state reveal"><p>No orders yet.</p></div>
<?php else: ?>
  <div class="table-wrap reveal">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Buyer</th><th>Items</th><th>Total (&#8377;)</th><th>Pay</th><th>Status</th><th>Set Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?php echo (int) $o['order_id']; ?></td>
          <td><?php echo htmlspecialchars($o['username']); ?></td>
          <td class="td-game"><?php echo htmlspecialchars($o['items'] ?? '—'); ?></td>
          <td class="td-amount">&#8377;<?php echo number_format((float) $o['total_amount'], 2); ?></td>
          <td><?php echo htmlspecialchars($o['payment_method']); ?></td>
          <td><span class="badge-<?php echo strtolower($o['order_status']); ?>"><?php echo htmlspecialchars($o['order_status']); ?></span></td>
          <td>
            <form method="post" action="admin.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="form" value="status">
              <input type="hidden" name="order_id" value="<?php echo (int) $o['order_id']; ?>">
              <select name="new_status">
                <?php foreach ($valid_statuses as $s): ?>
                  <option value="<?php echo $s; ?>"<?php echo ($o['order_status'] === $s) ? ' selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn btn-blue">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<h3 class="section-title reveal">&#10133; Add Game to Catalogue</h3>
<div class="form-wrap booking-card reveal">
  <form method="post" action="admin.php" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
    <input type="hidden" name="form" value="add_game">
    <div class="form-grid">
      <div class="form-group">
        <label for="gTitle">Title</label>
        <input type="text" id="gTitle" name="title" maxlength="150" required>
      </div>
      <div class="form-group">
        <label for="gCat">Category</label>
        <select id="gCat" name="category_id" required>
          <option value="">-- Select --</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int) $c['category_id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="gPlat">Platform</label>
        <input type="text" id="gPlat" name="platform" maxlength="100" placeholder="PC, PS5, Xbox" required>
      </div>
      <div class="form-group">
        <label for="gPrice">Price (&#8377;)</label>
        <input type="number" id="gPrice" name="price" min="1" step="0.01" required>
      </div>
      <div class="form-group">
        <label for="gDisc">Discount %</label>
        <input type="number" id="gDisc" name="discount" min="0" max="100" step="1" value="0">
      </div>
      <div class="form-group">
        <label for="gImg">Image path (optional)</label>
        <input type="text" id="gImg" name="image" maxlength="255" placeholder="assets/img/game.jpg">
      </div>
    </div>
    <div class="form-group form-group-full">
      <label for="gDesc">Description</label>
      <textarea id="gDesc" name="description" rows="3"></textarea>
    </div>
    <button type="submit" class="btn btn-green">Add Game</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
