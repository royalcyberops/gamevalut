<?php
// booking.php — Protected reservation page.
// Requires login via includes/auth.php FIRST, then loads DB + logged-in user details.
// Validates everything server-side and computes total from DB price (never trusts client).

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php'; // csrf_token() / csrf_verify()
require_once __DIR__ . '/includes/db.php';

$page_title  = 'Reserve a Game';
$active_page = 'booking';

$user_id = (int)($_SESSION['user_id'] ?? 0);

// ------------------------------------------------- logged-in user details ---
$full_name = '';
$mobile    = '';
try {
    $stmt = $conn->prepare('SELECT full_name, mobile FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    if ($row = $stmt->fetch()) {
        $full_name = $row['full_name'];
        $mobile    = $row['mobile'];
    }
} catch (PDOException $e) {
    // Non-fatal: form still renders, fields just won't be prefilled.
    $full_name = '';
    $mobile    = '';
}

// ------------------------------------------------------------- games list ---
$games = [];
try {
    $stmt = $conn->prepare('SELECT game_id, title, price, discount_percent FROM games ORDER BY title ASC');
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $games[] = $row;
    }
} catch (PDOException $e) {
    $games = [];
}

// Preselect from ?game_id= query string (e.g. Reserve button on index.php).
$sel_game = (int)($_GET['game_id'] ?? 0);

// ------------------------------------------------------------- form state ---
$success_msg = '';
$error_msg   = '';

// Repopulate on error.
$f_game_id      = $sel_game;
$f_qty          = 1;
$f_date         = '';
$f_time         = '';
$f_contact_name = $full_name;
$f_contact_phone = $mobile;
$f_notes        = '';

$today   = date('Y-m-d');
$maxdate = date('Y-m-d', strtotime('+60 days'));

// ------------------------------------------------------------- handle POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reject forged cross-site posts BEFORE any state change (the INSERT below).
    $csrf_ok = csrf_verify();

    $f_game_id       = (int)($_POST['game_id'] ?? 0);
    $f_qty           = (int)($_POST['quantity'] ?? 0);
    // is_string() guards: posting e.g. contact_name[]=x sends an array,
    // and trim(array) is a fatal TypeError on PHP 8.
    $f_date          = is_string($_POST['booking_date'] ?? '') ? trim($_POST['booking_date']) : '';
    $f_time          = is_string($_POST['booking_time'] ?? '') ? trim($_POST['booking_time']) : '';
    $f_contact_name  = is_string($_POST['contact_name'] ?? '') ? trim($_POST['contact_name']) : '';
    $f_contact_phone = is_string($_POST['contact_phone'] ?? '') ? trim($_POST['contact_phone']) : '';
    $f_notes         = is_string($_POST['notes'] ?? '') ? trim($_POST['notes']) : '';

    // 1. Game must exist — fetch authoritative price + discount.
    $db_price = null;
    $db_disc  = 0;
    try {
        $stmt = $conn->prepare('SELECT price, discount_percent FROM games WHERE game_id = ?');
        $stmt->execute([$f_game_id]);
        if ($grow = $stmt->fetch()) {
            $db_price = (float)$grow['price'];
            $db_disc  = (int)$grow['discount_percent'];
        }
    } catch (PDOException $e) {
        $db_price = null;
    }
    // 0. CSRF token must match the one stored in the session.
    if (!$csrf_ok) {
        $error_msg = 'Security check failed. Please reload the page and try again.';
    }
    // 1. Game must exist — fetch authoritative price + discount.
    elseif ($db_price === null) {
        $error_msg = 'Please select a valid game.';
    }
    // 2. Quantity 1-5.
    elseif ($f_qty < 1 || $f_qty > 5) {
        $error_msg = 'Quantity must be between 1 and 5.';
    }
    // 3. Date: valid Y-m-d, not in the past, max 60 days ahead.
    elseif (($d = DateTime::createFromFormat('Y-m-d', $f_date)) === false || $d->format('Y-m-d') !== $f_date) {
        $error_msg = 'Please choose a valid booking date (YYYY-MM-DD).';
    } elseif ($f_date < $today) {
        $error_msg = 'Booking date cannot be in the past.';
    } elseif ($f_date > $maxdate) {
        $error_msg = 'Booking date cannot be more than 60 days ahead.';
    }
    // 4. Time: HH:MM 24-hour.
    elseif (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $f_time)) {
        $error_msg = 'Please choose a valid booking time (HH:MM).';
    }
    // 5. Contact name: letters + spaces, min 3 chars.
    elseif (!preg_match('/^[A-Za-z ]{3,100}$/', $f_contact_name)) {
        $error_msg = 'Contact name must be at least 3 letters (letters and spaces only).';
    }
    // 6. Contact phone: Indian 10-digit mobile starting 6-9.
    elseif (!preg_match('/^[6-9]\d{9}$/', $f_contact_phone)) {
        $error_msg = 'Enter a valid 10-digit mobile number starting with 6-9.';
    }
    // 7. Notes optional, max 500 chars.
    elseif (mb_strlen($f_notes) > 500) {
        $error_msg = 'Notes cannot exceed 500 characters.';
    } else {
        // Server-side total (never trust client-sent amount).
        $clamped_disc = max(0, min(100, $db_disc));
        $total = round($db_price * (1 - $clamped_disc / 100) * $f_qty, 2);

        // TIME column needs HH:MM:SS; form gives HH:MM.
        $db_time = (strlen($f_time) === 5) ? $f_time . ':00' : $f_time;
        $notes_param = ($f_notes === '') ? null : $f_notes;

        try {
            $stmt = $conn->prepare(
                "INSERT INTO bookings (user_id, game_id, quantity, booking_date, booking_time, contact_name, contact_phone, notes, booking_status, total_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            $stmt->execute([$user_id, $f_game_id, $f_qty, $f_date, $db_time, $f_contact_name, $f_contact_phone, $notes_param, $total]);
            $booking_id = $conn->lastInsertId();

            $success_msg = 'Booking confirmed! Your booking ID is #' . (int)$booking_id . '. Total: ₹' . number_format($total, 2) . '. Status: Pending.';
            // Reset mutable fields after success (keep name/phone).
            $f_game_id = $sel_game;
            $f_qty = 1;
            $f_date = '';
            $f_time = '';
            $f_notes = '';
        } catch (PDOException $e) {
            $error_msg = 'Could not save your booking. Please try again.';
        }
    }

    // Keep the posted game selected in the dropdown.
    $sel_game = $f_game_id;
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="section-title">Reserve a Game</h2>
<p class="muted">Pick a title, choose your activation slot, and we will hold it in your vault.</p>

<?php if ($success_msg !== ''): ?>
  <p class="msg-success"><?php echo htmlspecialchars($success_msg); ?></p>
<?php endif; ?>
<?php if ($error_msg !== ''): ?>
  <p class="msg-error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if (empty($games)): ?>
  <p class="msg-error">No games available right now. Please try again later.</p>
<?php else: ?>

<div class="booking-layout">
  <!-- ============ LEFT: FORM ============ -->
  <div class="form-wrap booking-card">
    <h3 class="booking-card-title">Reservation details</h3>

    <form id="bookingForm" method="post" action="booking.php" onsubmit="return validateBooking()" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <!-- Game -->
      <div class="form-group form-group-full">
        <label for="gameSelect">Game</label>
        <select id="gameSelect" name="game_id" required>
          <option value="">-- Select a game --</option>
          <?php foreach ($games as $g): ?>
            <?php $gid = (int)$g['game_id']; ?>
            <option value="<?php echo $gid; ?>"
                    data-price="<?php echo htmlspecialchars($g['price']); ?>"
                    data-discount="<?php echo (int)$g['discount_percent']; ?>"
                    <?php echo ($gid === (int)$sel_game || $gid === $f_game_id) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($g['title']); ?> — ₹<?php echo htmlspecialchars(number_format((float)$g['price'], 2)); ?><?php echo ((int)$g['discount_percent'] > 0) ? ' (-' . (int)$g['discount_percent'] . '%)' : ''; ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="err" id="err-gameSelect"></span>
      </div>

      <div class="form-grid">
        <!-- Quantity -->
        <div class="form-group">
          <label for="qty">Quantity (1–5)</label>
          <input type="number" id="qty" name="quantity" min="1" max="5" step="1" value="<?php echo (int)$f_qty; ?>" required>
          <span class="err" id="err-qty"></span>
        </div>

        <!-- Date -->
        <div class="form-group">
          <label for="bookingDate">Preferred date</label>
          <input type="date" id="bookingDate" name="booking_date"
                 min="<?php echo htmlspecialchars($today); ?>"
                 max="<?php echo htmlspecialchars($maxdate); ?>"
                 value="<?php echo htmlspecialchars($f_date); ?>" required>
          <span class="err" id="err-bookingDate"></span>
        </div>

        <!-- Time -->
        <div class="form-group">
          <label for="bookingTime">Preferred time</label>
          <input type="time" id="bookingTime" name="booking_time" value="<?php echo htmlspecialchars($f_time); ?>" required>
          <span class="err" id="err-bookingTime"></span>
        </div>

        <!-- Contact name -->
        <div class="form-group">
          <label for="contactName">Contact name</label>
          <input type="text" id="contactName" name="contact_name" maxlength="100"
                 value="<?php echo htmlspecialchars($f_contact_name); ?>" placeholder="e.g. Rahul Sharma" required>
          <span class="err" id="err-contactName"></span>
        </div>

        <!-- Contact phone -->
        <div class="form-group">
          <label for="contactPhone">Contact phone</label>
          <input type="tel" id="contactPhone" name="contact_phone" maxlength="10"
                 value="<?php echo htmlspecialchars($f_contact_phone); ?>" placeholder="10-digit mobile" required>
          <span class="err" id="err-contactPhone"></span>
        </div>
      </div>

      <!-- Notes -->
      <div class="form-group form-group-full">
        <label for="notes">Notes <span class="label-optional">(optional, max 500 chars)</span></label>
        <textarea id="notes" name="notes" maxlength="500" rows="3" placeholder="Anything we should know?"><?php echo htmlspecialchars($f_notes); ?></textarea>
      </div>

      <!-- Submit lives in the aside via form="bookingForm"; no visible button here. -->
    </form>
  </div><!-- /.booking-card -->

  <!-- ============ RIGHT: LIVE ORDER SUMMARY ============ -->
  <aside class="order-summary">
    <h3 class="summary-title">Order Summary</h3>

    <div class="summary-row">
      <span>Unit price</span>
      <span id="pricePreview">—</span>
    </div>
    <div class="summary-row">
      <span>Quantity</span>
      <span id="qtyPreview">1</span>
    </div>

    <div class="summary-total">
      <span>Total</span>
      <strong id="totalPreview">—</strong>
    </div>

    <button type="submit" class="btn btn-warm btn-block" form="bookingForm">Confirm Booking</button>
    <p class="summary-note">&#128274; Price locked from the vault. Status starts as <em>Pending</em>.</p>
  </aside>
</div><!-- /.booking-layout -->

<?php endif; ?>

<script src="js/validation.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
