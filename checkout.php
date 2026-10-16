<?php
// checkout.php — GameVault checkout (Cash on Delivery).
// Requires login. Turns the session cart into an order + order_items rows
// inside one transaction, then clears the cart. Totals are computed from
// database prices — never from client-sent amounts.

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/db.php';

$page_title  = 'Checkout';
$active_page = 'checkout';

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Prefill contact details from the profile.
$profile_name = '';
$profile_phone = '';
try {
    $stmt = $conn->prepare('SELECT full_name, mobile FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) {
        $profile_name  = $row['full_name'];
        $profile_phone = $row['mobile'];
    }
} catch (PDOException $e) {
    $profile_name = '';
    $profile_phone = '';
}

// Form state (repopulated on error).
$f_name    = $profile_name;
$f_phone   = $profile_phone;
$f_address = '';
$f_date    = '';
$f_time    = '';
$f_notes   = '';
$error_msg = '';

$today   = date('Y-m-d');
$maxdate = date('Y-m-d', strtotime('+60 days'));

// ---- Load cart lines with authoritative prices ----
function checkout_lines($conn, $cart)
{
    $lines = [];
    $total = 0.00;
    if (empty($cart)) {
        return [$lines, $total];
    }
    $ids  = array_keys($cart);
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare(
        "SELECT game_id, title, price, discount_percent FROM games WHERE game_id IN ($ph)"
    );
    $stmt->execute($ids);
    while ($g = $stmt->fetch()) {
        $gid  = (int) $g['game_id'];
        if (!isset($cart[$gid])) {
            continue; // game removed from catalogue after being added to cart
        }
        $qty  = max(1, min(5, (int) $cart[$gid]));
        $disc = max(0, min(100, (int) $g['discount_percent']));
        $unit = round((float) $g['price'] * (1 - $disc / 100), 2);
        $lines[] = [
            'game_id' => $gid,
            'title'   => $g['title'],
            'qty'     => $qty,
            'unit'    => $unit,
        ];
        $total += $unit * $qty;
    }
    return [$lines, round($total, 2)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_ok = csrf_verify();

    // is_string() guards: posted arrays would fatal trim() on PHP 8.
    $f_name    = is_string($_POST['contact_name'] ?? '') ? trim($_POST['contact_name']) : '';
    $f_phone   = is_string($_POST['contact_phone'] ?? '') ? trim($_POST['contact_phone']) : '';
    $f_address = is_string($_POST['address'] ?? '') ? trim($_POST['address']) : '';
    $f_date    = is_string($_POST['delivery_date'] ?? '') ? trim($_POST['delivery_date']) : '';
    $f_time    = is_string($_POST['delivery_time'] ?? '') ? trim($_POST['delivery_time']) : '';
    $f_notes   = is_string($_POST['notes'] ?? '') ? trim($_POST['notes']) : '';

    try {
        list($lines, $cart_total) = checkout_lines($conn, cart_get());
    } catch (PDOException $e) {
        $lines = [];
        $cart_total = 0.00;
    }

    if (!$csrf_ok) {
        $error_msg = 'Security check failed. Please reload the page and try again.';
    } elseif (empty($lines)) {
        $error_msg = 'Your cart is empty or the games are no longer available.';
    } elseif (!preg_match('/^[A-Za-z ]{3,100}$/', $f_name)) {
        $error_msg = 'Contact name must be at least 3 letters (letters and spaces only).';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $f_phone)) {
        $error_msg = 'Enter a valid 10-digit mobile number starting with 6-9.';
    } elseif (mb_strlen($f_address) < 10 || mb_strlen($f_address) > 300) {
        $error_msg = 'Delivery address must be 10-300 characters.';
    } elseif (($d = DateTime::createFromFormat('Y-m-d', $f_date)) === false || $d->format('Y-m-d') !== $f_date) {
        $error_msg = 'Please choose a valid delivery date (YYYY-MM-DD).';
    } elseif ($f_date < $today) {
        $error_msg = 'Delivery date cannot be in the past.';
    } elseif ($f_date > $maxdate) {
        $error_msg = 'Delivery date cannot be more than 60 days ahead.';
    } elseif (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $f_time)) {
        $error_msg = 'Please choose a valid delivery time (HH:MM).';
    } elseif (mb_strlen($f_notes) > 500) {
        $error_msg = 'Notes cannot exceed 500 characters.';
    } else {
        // All valid: place the order atomically (order + items or nothing).
        $db_time     = (strlen($f_time) === 5) ? $f_time . ':00' : $f_time;
        $notes_param = ($f_notes === '') ? null : $f_notes;
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare(
                "INSERT INTO orders (user_id, contact_name, contact_phone, address,
                                     delivery_date, delivery_time, notes,
                                     payment_method, order_status, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'COD', 'Pending', ?)"
            );
            $stmt->execute([$user_id, $f_name, $f_phone, $f_address,
                            $f_date, $db_time, $notes_param, $cart_total]);
            $order_id = (int) $conn->lastInsertId();

            $item = $conn->prepare(
                'INSERT INTO order_items (order_id, game_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($lines as $l) {
                $item->execute([$order_id, $l['game_id'], $l['qty'], $l['unit']]);
            }
            $conn->commit();

            cart_clear(); // order placed: empty the cart
            header('Location: dashboard.php?msg=order_placed&order_id=' . $order_id);
            exit;
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error_msg = 'Could not place your order. Please try again.';
        }
    }
}

// Refresh summary for display (GET or failed POST).
try {
    list($lines, $cart_total) = checkout_lines($conn, cart_get());
} catch (PDOException $e) {
    $lines = [];
    $cart_total = 0.00;
    if ($error_msg === '') {
        $error_msg = 'Could not load your cart right now. Please try again later.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="section-title">Checkout</h2>
<p class="muted">Cash on Delivery — pay when your games arrive.</p>

<?php if ($error_msg !== ''): ?>
  <p class="msg-error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if (empty($lines)): ?>
  <div class="empty-state reveal">
    <p>Your cart is empty.</p>
    <a href="index.php" class="btn btn-warm btn-shine">Browse games</a>
  </div>
<?php else: ?>
<div class="booking-layout">
  <div class="form-wrap booking-card">
    <h3 class="booking-card-title">Delivery details</h3>

    <form id="checkoutForm" method="post" action="checkout.php" onsubmit="return validateCheckout()" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

      <div class="form-grid">
        <div class="form-group">
          <label for="contactName">Contact name</label>
          <input type="text" id="contactName" name="contact_name" maxlength="100"
                 value="<?php echo htmlspecialchars($f_name); ?>" placeholder="e.g. Rahul Sharma" required>
          <span class="err" id="err-contactName"></span>
        </div>

        <div class="form-group">
          <label for="contactPhone">Contact phone</label>
          <input type="tel" id="contactPhone" name="contact_phone" maxlength="10"
                 value="<?php echo htmlspecialchars($f_phone); ?>" placeholder="10-digit mobile" required>
          <span class="err" id="err-contactPhone"></span>
        </div>

        <div class="form-group">
          <label for="deliveryDate">Preferred delivery date</label>
          <input type="date" id="deliveryDate" name="delivery_date"
                 min="<?php echo htmlspecialchars($today); ?>"
                 max="<?php echo htmlspecialchars($maxdate); ?>"
                 value="<?php echo htmlspecialchars($f_date); ?>" required>
          <span class="err" id="err-deliveryDate"></span>
        </div>

        <div class="form-group">
          <label for="deliveryTime">Preferred delivery time</label>
          <input type="time" id="deliveryTime" name="delivery_time" value="<?php echo htmlspecialchars($f_time); ?>" required>
          <span class="err" id="err-deliveryTime"></span>
        </div>
      </div>

      <div class="form-group form-group-full">
        <label for="address">Delivery address</label>
        <textarea id="address" name="address" maxlength="300" rows="3" placeholder="Flat, street, area, city, PIN" required><?php echo htmlspecialchars($f_address); ?></textarea>
        <span class="err" id="err-address"></span>
      </div>

      <div class="form-group form-group-full">
        <label for="notes">Notes <span class="label-optional">(optional, max 500 chars)</span></label>
        <textarea id="notes" name="notes" maxlength="500" rows="3" placeholder="Anything we should know?"><?php echo htmlspecialchars($f_notes); ?></textarea>
      </div>
    </form>
  </div>

  <aside class="order-summary">
    <h3 class="summary-title">Order Summary</h3>
    <?php foreach ($lines as $l): ?>
      <div class="summary-row">
        <span><?php echo htmlspecialchars($l['title']); ?> &times; <?php echo (int) $l['qty']; ?></span>
        <span>&#8377;<?php echo number_format($l['unit'] * $l['qty'], 2); ?></span>
      </div>
    <?php endforeach; ?>
    <div class="summary-row">
      <span>Payment</span>
      <span>Cash on Delivery</span>
    </div>
    <div class="summary-total">
      <span>Total</span>
      <strong>&#8377;<?php echo number_format($cart_total, 2); ?></strong>
    </div>

    <button type="submit" class="btn btn-warm btn-block" form="checkoutForm">Place Order (COD)</button>
    <p class="summary-note">&#128274; Price locked from the vault. Status starts as <em>Pending</em>.</p>
  </aside>
</div>
<?php endif; ?>

<script src="js/validation.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
