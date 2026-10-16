<?php
// checkout.php — GameVault checkout (Card / UPI).
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
$f_pay     = 'CARD';
$f_card_number = '';
$f_card_expiry = '';
$f_card_cvv    = '';
$f_upi         = '';
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
    // Single-read: trim($_POST['x']) in the true-branch warns on missing keys.
    $in_name    = $_POST['contact_name'] ?? '';
    $in_phone   = $_POST['contact_phone'] ?? '';
    $in_address = $_POST['address'] ?? '';
    $in_date    = $_POST['delivery_date'] ?? '';
    $in_time    = $_POST['delivery_time'] ?? '';
    $in_notes   = $_POST['notes'] ?? '';
    $f_name    = is_string($in_name)    ? trim($in_name)    : '';
    $f_phone   = is_string($in_phone)   ? trim($in_phone)   : '';
    $f_address = is_string($in_address) ? trim($in_address) : '';
    $f_date    = is_string($in_date)    ? trim($in_date)    : '';
    $f_time    = is_string($in_time)    ? trim($in_time)    : '';
    $f_notes   = is_string($in_notes)   ? trim($in_notes)   : '';
    // Payment choice: CARD or UPI (COD removed).
    // Single-read: the ?? in the check must not be re-read in the branch.
    $pay_posted = $_POST['payment_method'] ?? '';
    $f_pay_raw = is_string($pay_posted) ? strtoupper(trim($pay_posted)) : '';
    $f_pay = ($f_pay_raw === 'UPI') ? 'UPI' : 'CARD';
    $in_card_number = $_POST['card_number'] ?? '';
    $in_card_expiry = $_POST['card_expiry'] ?? '';
    $in_card_cvv    = $_POST['card_cvv'] ?? '';
    $in_upi         = $_POST['upi_id'] ?? '';
    $f_card_number = is_string($in_card_number) ? trim($in_card_number) : '';
    $f_card_expiry = is_string($in_card_expiry) ? trim($in_card_expiry) : '';
    $f_card_cvv    = is_string($in_card_cvv) ? trim($in_card_cvv) : '';
    $f_upi         = is_string($in_upi) ? trim($in_upi) : '';
    // Normalise card number for validation (allow spaces/dashes in input).
    $card_digits = preg_replace('/[\s-]/', '', $f_card_number);

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
    } elseif ($f_pay_raw !== 'CARD' && $f_pay_raw !== 'UPI') {
        $error_msg = 'Please choose a payment method: Card or UPI.';
    } elseif ($f_pay_raw === 'CARD' && !preg_match('/^\d{16}$/', $card_digits)) {
        $error_msg = 'Enter a valid 16-digit card number.';
    } elseif ($f_pay_raw === 'CARD' && !preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $f_card_expiry)) {
        $error_msg = 'Enter card expiry as MM/YY.';
    } elseif ($f_pay_raw === 'CARD' && !preg_match('/^\d{3,4}$/', $f_card_cvv)) {
        $error_msg = 'Enter a valid 3-4 digit CVV.';
    } elseif ($f_pay_raw === 'UPI' && !preg_match('/^[a-zA-Z0-9._-]{2,50}@[a-zA-Z]{2,30}$/', $f_upi)) {
        $error_msg = 'Enter a valid UPI ID (e.g. name@okbank).';
    } else {
        // Expiry must be in the future (CARD only).
        if ($f_pay_raw === 'CARD') {
            $exp_m = 0; $exp_y = 0;
            if (preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/', $f_card_expiry, $m)) {
                $exp_m = (int) $m[1];
                $exp_y = (int) $m[2];
                if ($exp_y < 100) { $exp_y += 2000; }
            }
            $cur_y = (int) date('Y'); $cur_m = (int) date('n');
            if ($exp_y < $cur_y || ($exp_y === $cur_y && $exp_m < $cur_m)) {
                $error_msg = 'Card expiry must be in the future.';
            }
        }
    }
    if ($error_msg === '') {
        // All valid: place the order atomically (order + items or nothing).
        // Only payment_method is stored — card/UPI details are validated
        // and never saved.
        $db_time     = (strlen($f_time) === 5) ? $f_time . ':00' : $f_time;
        $notes_param = ($f_notes === '') ? null : $f_notes;
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare(
                "INSERT INTO orders (user_id, contact_name, contact_phone, address,
                                     delivery_date, delivery_time, notes,
                                     payment_method, order_status, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            $stmt->execute([$user_id, $f_name, $f_phone, $f_address,
                            $f_date, $db_time, $notes_param, $f_pay_raw, $cart_total]);
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
<p class="muted">Secure checkout — pay by Card or UPI.</p>

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

      <div class="form-group form-group-full">
        <span class="user-key">Payment method</span>
        <label class="pay-option">
          <input type="radio" name="payment_method" value="CARD"<?php echo ($f_pay !== 'UPI') ? ' checked' : ''; ?>>
          <span><strong>Card</strong> — credit / debit card.</span>
        </label>
        <label class="pay-option">
          <input type="radio" name="payment_method" value="UPI"<?php echo ($f_pay === 'UPI') ? ' checked' : ''; ?>>
          <span><strong>UPI</strong> — pay instantly with any UPI app.</span>
        </label>
        <span class="err" id="err-payment"></span>
      </div>

      <div id="cardFields" class="form-grid"<?php echo ($f_pay === 'UPI') ? ' hidden' : ''; ?>>
        <div class="form-group">
          <label for="cardNumber">Card number</label>
          <input type="text" id="cardNumber" name="card_number" inputmode="numeric" maxlength="19"
                 value="<?php echo htmlspecialchars($f_card_number); ?>" placeholder="1234 5678 9012 3456">
          <span class="err" id="err-cardNumber"></span>
        </div>
        <div class="form-group">
          <label for="cardExpiry">Expiry (MM/YY)</label>
          <input type="text" id="cardExpiry" name="card_expiry" maxlength="7"
                 value="<?php echo htmlspecialchars($f_card_expiry); ?>" placeholder="MM/YY">
          <span class="err" id="err-cardExpiry"></span>
        </div>
        <div class="form-group">
          <label for="cardCvv">CVV</label>
          <input type="password" id="cardCvv" name="card_cvv" inputmode="numeric" maxlength="4"
                 value="" placeholder="3-4 digits" autocomplete="off">
          <span class="err" id="err-cardCvv"></span>
        </div>
      </div>

      <div id="upiFields" class="form-grid"<?php echo ($f_pay !== 'UPI') ? ' hidden' : ''; ?>>
        <div class="form-group form-group-full">
          <label for="upiId">UPI ID</label>
          <input type="text" id="upiId" name="upi_id" maxlength="80"
                 value="<?php echo htmlspecialchars($f_upi); ?>" placeholder="name@okbank">
          <span class="err" id="err-upiId"></span>
        </div>
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
      <span id="paySummary"><?php echo ($f_pay === 'UPI') ? 'UPI' : 'Card'; ?></span>
    </div>
    <div class="summary-total">
      <span>Total</span>
      <strong>&#8377;<?php echo number_format($cart_total, 2); ?></strong>
    </div>

    <button type="submit" class="btn btn-warm btn-block" form="checkoutForm">Pay &amp; Place Order</button>
    <p class="summary-note">&#128274; Price locked from the vault. Status starts as <em>Pending</em>.</p>
  </aside>
</div>
<?php endif; ?>

<?php $val_v = @filemtime(__DIR__ . '/js/validation.js') ?: time(); ?>
<script src="js/validation.js?v=<?php echo (int)$val_v; ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
