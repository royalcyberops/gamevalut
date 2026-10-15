<?php
// dashboard.php — GameVault "My Dashboard" (protected page).
// Shows: welcome banner, user info card, stat boxes, quick links,
// and the user's booking history table with Cancel option.
// Procedural PHP + PDO prepared statements. Viva points:
//  - require auth.php FIRST (session guard + no-cache headers).
//  - Every query uses prepared statements bound to $_SESSION['user_id'].
//  - Cancel updates ONLY own Pending booking (booking_id + user_id + status).
//  - htmlspecialchars() on all echoed DB values (XSS prevention).

require_once __DIR__ . '/includes/auth.php'; // redirects to login if not logged in
require_once __DIR__ . '/includes/csrf.php'; // csrf_token() / csrf_verify()
require_once __DIR__ . '/includes/db.php';   // gives $conn

$user_id = (int) $_SESSION['user_id'];
$message   = '';
$msg_class = '';

// ---- Handle booking cancellation (POST from Cancel button) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    if (!csrf_verify()) {
        // CSRF check failed — reject before touching the database.
        $message   = 'Security check failed. Please reload the page and try again.';
        $msg_class = 'msg-error';
    } else {
        $cancel_id = (int) $_POST['cancel_id'];
        // Only the owner can cancel, and only while booking is still Pending.
        $stmt = $conn->prepare(
            "UPDATE bookings SET booking_status = 'Cancelled'
             WHERE booking_id = ? AND user_id = ? AND booking_status = 'Pending'"
        );
        $stmt->execute([$cancel_id, $user_id]);
        if ($stmt->rowCount() > 0) {
            $message   = 'Booking #' . $cancel_id . ' cancelled.';
            $msg_class = 'msg-success';
        } else {
            $message   = 'Cannot cancel this booking (already confirmed/cancelled).';
            $msg_class = 'msg-error';
        }
    }
}

// ---- Fetch logged-in user's profile ----
// (auth.php already verified this user_id exists in the users table,
//  so fetch() is guaranteed to return a row here.)
$user = null;
$stmt = $conn->prepare(
    'SELECT full_name, email, mobile, username, created_at FROM users WHERE user_id = ?'
);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ---- Fetch user's bookings (newest first) with game title ----
$bookings = [];
$stmt = $conn->prepare(
    'SELECT b.*, g.title FROM bookings b
     JOIN games g ON g.game_id = b.game_id
     WHERE b.user_id = ? ORDER BY b.created_at DESC'
);
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $bookings[] = $row;
}

// ---- Stats: total bookings + total spent (excluding Cancelled) ----
$total_bookings = count($bookings);
$total_spent    = 0.00;
foreach ($bookings as $b) {
    if ($b['booking_status'] !== 'Cancelled') {
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
    <a href="booking.php" class="btn btn-warm">+ New Booking</a>
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
    <p class="stat-num"><?php echo (int) $total_bookings; ?></p>
    <p class="stat-label">Total Bookings</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#128176;</span>
    <p class="stat-num">&#8377;<?php echo number_format($total_spent, 2); ?></p>
    <p class="stat-label">Total Spent</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#9203;</span>
    <!-- count() REQUIRED: casting the array_filter() array to int gives 1/0, not the number of matches -->
    <p class="stat-num"><?php echo count(array_filter($bookings, fn($b) => $b['booking_status'] === 'Pending')); ?></p>
    <p class="stat-label">Pending</p>
  </div>
  <div class="stat-box warm">
    <span class="stat-icon">&#9989;</span>
    <p class="stat-num"><?php echo count(array_filter($bookings, fn($b) => $b['booking_status'] === 'Confirmed')); ?></p>
    <p class="stat-label">Confirmed</p>
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

<!-- ============ BOOKINGS TABLE ============ -->
<h3 class="section-title reveal">&#128203; My Bookings</h3>

<?php if (empty($bookings)): ?>
  <div class="empty-state reveal">
    <p>No bookings yet.</p>
    <a href="booking.php" class="btn btn-warm btn-shine">Reserve your first game</a>
  </div>
<?php else: ?>
  <div class="table-wrap reveal">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Game</th><th>Qty</th><th>Date</th><th>Time</th><th>Total (&#8377;)</th><th>Status</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td>#<?php echo (int) $b['booking_id']; ?></td>
          <td class="td-game"><?php echo htmlspecialchars($b['title']); ?></td>
          <td><?php echo (int) $b['quantity']; ?></td>
          <td><?php echo htmlspecialchars(date('d M Y', strtotime($b['booking_date']))); ?></td>
          <td><?php echo htmlspecialchars(date('h:i A', strtotime($b['booking_time']))); ?></td>
          <td class="td-amount">&#8377;<?php echo number_format((float) $b['total_amount'], 2); ?></td>
          <td>
            <?php
            // Status badge class: badge-pending / badge-confirmed / badge-cancelled
            $badge = 'badge-' . strtolower($b['booking_status']);
            ?>
            <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($b['booking_status']); ?></span>
          </td>
          <td>
            <?php if ($b['booking_status'] === 'Pending'): ?>
              <!-- Cancel only allowed while Pending; confirm() avoids accidents -->
              <form method="post" action="dashboard.php" onsubmit="return confirm('Cancel booking #<?php echo (int) $b['booking_id']; ?>?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="cancel_id" value="<?php echo (int) $b['booking_id']; ?>">
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
