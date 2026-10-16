<?php
// dashboard.php — GameVault "My Dashboard" (protected page).
// Shows: welcome banner, user info card, stat boxes, quick links,
// and the user's order history table with Cancel option.
// Procedural PHP + PDO prepared statements. Viva points:
//  - require auth.php FIRST (session guard + no-cache headers).
//  - Every query uses prepared statements bound to $_SESSION['user_id'].
//  - Cancel updates ONLY own Pending order (order_id + user_id + status).
//  - htmlspecialchars() on all echoed DB values (XSS prevention).

require_once __DIR__ . '/includes/auth.php'; // redirects to login if not logged in
require_once __DIR__ . '/includes/csrf.php'; // csrf_token() / csrf_verify()
require_once __DIR__ . '/includes/db.php';   // gives $conn

$user_id = (int) $_SESSION['user_id'];
$message   = '';
$msg_class = '';

// ---- Flash messages from redirects (order placed / admin denied) ----
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'order_placed') {
        $oid = (is_numeric($_GET['order_id'] ?? 0)) ? (int) $_GET['order_id'] : 0;
        $message   = 'Order placed! Your order ID is #' . $oid . '. Pay in cash on delivery.';
        $msg_class = 'msg-success';
    } elseif ($_GET['msg'] === 'forbidden') {
        $message   = 'Staff only — you do not have admin access.';
        $msg_class = 'msg-error';
    }
}

// ---- Handle order cancellation (POST from Cancel button) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!csrf_verify()) {
        // CSRF check failed — reject before touching the database.
        $message   = 'Security check failed. Please reload the page and try again.';
        $msg_class = 'msg-error';
    } else {
        $cancel_id = (int) $_POST['cancel_id'];
        // Only the owner can cancel, and only while the order is still Pending.
        try {
            $stmt = $conn->prepare(
                "UPDATE orders SET order_status = 'Cancelled'
                 WHERE order_id = ? AND user_id = ? AND order_status = 'Pending'"
            );
            $stmt->execute([$cancel_id, $user_id]);
            if ($stmt->rowCount() > 0) {
                $message   = 'Order #' . $cancel_id . ' cancelled.';
                $msg_class = 'msg-success';
            } else {
                $message   = 'Cannot cancel this order (already confirmed/delivered/cancelled).';
                $msg_class = 'msg-error';
            }
        } catch (PDOException $e) {
            $message   = 'Could not cancel your order. Please try again.';
            $msg_class = 'msg-error';
        }
    }
}

// ---- Fetch logged-in user's profile ----
// (auth.php already verified this user_id exists in the users table,
//  so fetch() is guaranteed to return a row here.)
$user = null;
try {
    $stmt = $conn->prepare(
        'SELECT full_name, email, mobile, username, created_at FROM users WHERE user_id = ?'
    );
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = null; // page still renders with session fallback + friendly notice below
    if ($message === '') {
        $message   = 'Could not load your profile right now. Please try again later.';
        $msg_class = 'msg-error';
    }
}

// ---- Fetch user's orders (newest first) with item summary ----
$orders = [];
try {
    $stmt = $conn->prepare(
        'SELECT o.*,
                (SELECT GROUP_CONCAT(CONCAT(g.title, " x", i.quantity) SEPARATOR ", ")
                 FROM order_items i JOIN games g ON g.game_id = i.game_id
                 WHERE i.order_id = o.order_id) AS items
         FROM orders o
         WHERE o.user_id = ? ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch()) {
        $orders[] = $row;
    }
} catch (PDOException $e) {
    $orders = []; // page still renders stats + empty state instead of a 500
    if ($message === '') {
        $message   = 'Could not load your orders right now. Please try again later.';
        $msg_class = 'msg-error';
    }
}

// ---- Stats: total orders + total spent (excluding Cancelled) ----
$total_orders = count($orders);
$total_spent  = 0.00;
foreach ($orders as $b) {
    if ($b['order_status'] !== 'Cancelled') {
        $total_spent += (float) $b['total_amount'];
    }
}

$page_title  = 'Dashboard';
$active_page = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ============ WELCOME BANNER ============ -->
<div class="dash-welcome reveal">
  <div>
    <p class="dash-eyebrow">Welcome back</p>
    <h2 class="dash-name"><?php echo htmlspecialchars($user['full_name'] ?? $_SESSION['name'] ?? 'Gamer'); ?></h2>
    <p class="dash-sub">Member since <?php echo htmlspecialchars(date('d M Y', strtotime($user['created_at'] ?? 'now'))); ?></p>
  </div>
  <div class="dash-actions">
    <a href="index.php" class="btn btn-warm">+ Shop Games</a>
    <!-- Logout is state-changing: POST + CSRF token (a plain link would be CSRF-able). -->
    <form method="post" action="logout.php" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <button type="submit" class="btn btn-ghost">Logout</button>
    </form>
  </div>
</div>

<?php if ($message !== ''): ?>
  <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<!-- ============ STAT BOXES ============ -->
<div class="stat-row reveal">
  <div class="stat-box warm">
    <span class="stat-icon">&#128722;</span>
    <p class="stat-num"><?php echo (int) $total_orders; ?></p>
    <p class="stat-label">Total Orders</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#128176;</span>
    <p class="stat-num">&#8377;<?php echo number_format($total_spent, 2); ?></p>
    <p class="stat-label">Total Spent</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#9203;</span>
    <p class="stat-num"><?php echo count(array_filter($orders, fn($b) => $b['order_status'] === 'Pending')); ?></p>
    <p class="stat-label">Pending</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#9989;</span>
    <p class="stat-num"><?php echo count(array_filter($orders, fn($b) => $b['order_status'] === 'Delivered')); ?></p>
    <p class="stat-label">Delivered</p>
  </div>
</div>

<!-- ============ USER INFO CARD ============ -->
<div class="user-card reveal">
  <h3>&#128100; My Account</h3>
  <div class="user-grid">
    <p><span class="user-key">Full Name</span><span class="user-val"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></span></p>
    <p><span class="user-key">Username</span><span class="user-val"><?php echo htmlspecialchars($user['username'] ?? ''); ?></span></p>
    <p><span class="user-key">Email</span><span class="user-val"><?php echo htmlspecialchars($user['email'] ?? ''); ?></span></p>
    <p><span class="user-key">Mobile</span><span class="user-val"><?php echo htmlspecialchars($user['mobile'] ?? ''); ?></span></p>
  </div>
</div>

<!-- ============ ORDERS TABLE ============ -->
<h3 class="section-title reveal">&#128203; My Orders</h3>

<?php if (empty($orders)): ?>
  <div class="empty-state reveal">
    <p>No orders yet.</p>
    <a href="index.php" class="btn btn-warm btn-shine">Shop your first game</a>
  </div>
<?php else: ?>
  <div class="table-wrap reveal">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Items</th><th>Total (&#8377;)</th><th>Pay</th><th>Status</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $b): ?>
        <tr>
          <td>#<?php echo (int) $b['order_id']; ?></td>
          <td class="td-game"><?php echo htmlspecialchars($b['items'] ?? '—'); ?></td>
          <td class="td-amount">&#8377;<?php echo number_format((float) $b['total_amount'], 2); ?></td>
          <td><?php echo htmlspecialchars($b['payment_method']); ?></td>
          <td>
            <?php
            // Status badge class: badge-pending / badge-confirmed / badge-delivered / badge-cancelled
            $badge = 'badge-' . strtolower($b['order_status']);
            ?>
            <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($b['order_status']); ?></span>
          </td>
          <td>
            <?php if ($b['order_status'] === 'Pending'): ?>
              <!-- Cancel only allowed while Pending; confirm() avoids accidents -->
              <form method="post" action="dashboard.php" onsubmit="return confirm('Cancel order #<?php echo (int) $b['order_id']; ?>?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="cancel_id" value="<?php echo (int) $b['order_id']; ?>">
                <button type="submit" class="btn-cancel">Cancel</button>
              </form>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
