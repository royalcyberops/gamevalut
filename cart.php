<?php
// cart.php — GameVault shopping cart (session-based).
// Add/update/remove lines here, then go to checkout.php to place the order.
// Prices are re-read from the database on every load — the session only
// stores game_id => qty, so prices can never be tampered with client-side.

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/db.php';

$page_title  = 'My Cart';
$active_page = 'cart';

$message   = '';
$msg_class = '';

// ---- Handle cart actions (all POST + CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $message   = 'Security check failed. Please reload the page and try again.';
        $msg_class = 'msg-error';
    } else {
        $raw_action = $_POST['action'] ?? '';
        $action = is_string($raw_action) ? $raw_action : '';
        // Single-read guards: re-reading $_POST['x'] in the true-branch after
        // a ?? check still warns when the key is missing (index.php add-forms
        // send no quantity), so read once into a temp first.
        $raw_game = $_POST['game_id'] ?? 0;
        $raw_qty  = $_POST['quantity'] ?? 1;
        // is_numeric() guard: ?game_id[]=x style arrays must not reach (int) casts.
        $game_id = (is_numeric($raw_game)) ? (int) $raw_game : 0;
        $qty     = (is_numeric($raw_qty)) ? (int) $raw_qty : 1;

        if ($action === 'add' && $game_id > 0) {
            // Only add games that actually exist (price lookup is authoritative).
            try {
                $chk = $conn->prepare('SELECT game_id FROM games WHERE game_id = ?');
                $chk->execute([$game_id]);
                if ($chk->fetch() !== false) {
                    cart_add($game_id, $qty);
                    $message   = 'Added to cart.';
                    $msg_class = 'msg-success';
                } else {
                    $message   = 'That game does not exist.';
                    $msg_class = 'msg-error';
                }
            } catch (PDOException $e) {
                $message   = 'Could not update your cart. Please try again.';
                $msg_class = 'msg-error';
            }
        } elseif ($action === 'update' && $game_id > 0) {
            cart_set_qty($game_id, $qty);
            if ($qty <= 0) {
                $message   = 'Removed from cart.';
            } else {
                $message   = 'Cart updated.';
            }
            $msg_class = 'msg-success';
        } elseif ($action === 'remove' && $game_id > 0) {
            cart_remove($game_id);
            $message   = 'Removed from cart.';
            $msg_class = 'msg-success';
        } elseif ($action === 'clear') {
            cart_clear();
            $message   = 'Cart cleared.';
            $msg_class = 'msg-success';
        }
    }
}

// ---- Load cart lines with authoritative prices ----
$lines = []; // each: game_id, title, image, price, discount, qty, unit, line_total
$cart_total = 0.00;
$cart = cart_get();
if (!empty($cart)) {
    try {
        $ids  = array_keys($cart);
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare(
            "SELECT game_id, title, image, price, discount_percent FROM games WHERE game_id IN ($ph)"
        );
        $stmt->execute($ids);
        $found_ids = [];
        while ($g = $stmt->fetch()) {
            $gid  = (int) $g['game_id'];
            $qty  = max(1, min(5, (int) ($cart[$gid] ?? 1)));
            $disc = max(0, min(100, (int) $g['discount_percent']));
            $unit = round((float) $g['price'] * (1 - $disc / 100), 2);
            $lines[] = [
                'game_id'    => $gid,
                'title'      => $g['title'],
                'image'      => $g['image'],
                'price'      => (float) $g['price'],
                'discount'   => $disc,
                'qty'        => $qty,
                'unit'       => $unit,
                'line_total' => round($unit * $qty, 2),
            ];
            $cart_total += $unit * $qty;
            $found_ids[] = $gid;
        }
        // Prune ghost ids (in session but missing from games) so nav
        // count, rendered rows, and totals always agree.
        cart_prune($found_ids);
        $cart_total = round($cart_total, 2);
    } catch (PDOException $e) {
        $lines = [];
        $message   = 'Could not load your cart right now. Please try again later.';
        $msg_class = 'msg-error';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 class="section-title">My Cart</h2>
<p class="muted">Review your games, then check out with Card or UPI.</p>

<?php if ($message !== ''): ?>
  <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<?php if (empty($lines)): ?>
  <div class="empty-state reveal">
    <p>Your cart is empty.</p>
    <a href="index.php" class="btn btn-warm btn-shine">Browse games</a>
  </div>
<?php else: ?>
  <div class="table-wrap reveal">
  <table>
    <thead>
      <tr>
        <th>Game</th><th>Unit (&#8377;)</th><th>Qty</th><th>Total (&#8377;)</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td class="td-game">
            <?php echo htmlspecialchars($l['title']); ?>
            <?php if ($l['discount'] > 0): ?>
              <span class="discount-badge">-<?php echo (int) $l['discount']; ?>%</span>
            <?php endif; ?>
          </td>
          <td>&#8377;<?php echo number_format($l['unit'], 2); ?></td>
          <td>
            <form method="post" action="cart.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="game_id" value="<?php echo (int) $l['game_id']; ?>">
              <input type="number" name="quantity" min="1" max="5" step="1" value="<?php echo (int) $l['qty']; ?>" style="width:64px;" onchange="this.form.submit()">
            </form>
          </td>
          <td class="td-amount">&#8377;<?php echo number_format($l['line_total'], 2); ?></td>
          <td>
            <form method="post" action="cart.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="game_id" value="<?php echo (int) $l['game_id']; ?>">
              <button type="submit" class="btn-cancel">Remove</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <aside class="order-summary reveal">
    <h3 class="summary-title">Order Summary</h3>
    <div class="summary-row">
      <span>Items</span>
      <span><?php echo (int) array_sum(array_column($lines, 'qty')); ?></span>
    </div>
    <div class="summary-total">
      <span>Total</span>
      <strong>&#8377;<?php echo number_format($cart_total, 2); ?></strong>
    </div>
    <a href="checkout.php" class="btn btn-warm btn-block">Proceed to Checkout</a>
    <p class="summary-note">&#128274; Prices locked from the vault. Pay securely by Card or UPI.</p>
  </aside>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
