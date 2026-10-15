<?php
// includes/auth.php
// Session guard for protected pages (dashboard.php, booking.php).
// Usage: require_once __DIR__ . '/auth.php';  (at the very top)
//
// Viva points:
//  - No-cache headers stop the Back button showing protected pages
//    after logout (session destroyed server-side).
//  - The guard does NOT trust $_SESSION alone. It re-checks that the
//    user_id still exists in the users table. This prevents a stale
//    session (e.g. account deleted) from reaching the DB layer, where
//    the bookings FK (REFERENCES users.user_id) would throw ERROR 1452
//    and fail every INSERT with a generic "Could not save" message.

require_once __DIR__ . '/session.php'; // hardened bootstrap (the ONLY session_start)

// Prevent Back-button showing protected pages after logout (viva point).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Not logged in? Send to login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?msg=login_required');
    exit;
}

// ---- Session expiry (R1): absolute lifetime + idle timeout ----
// Absolute: max 2 hours since login, regardless of activity.
// Idle:     max 30 minutes between requests while authenticated.
// On expiry the whole session is destroyed (user_id included), so the
// redirect above cannot loop — login.php only redirects back when a live
// user_id still exists.
$now         = time();
$login_at    = (int) ($_SESSION['login_at'] ?? 0);
$last_active = (int) ($_SESSION['last_activity'] ?? $login_at);
if ($login_at === 0
    || ($now - $login_at) > 7200      // absolute 2 hours
    || ($now - $last_active) > 1800) { // idle 30 minutes
    $_SESSION = [];
    session_unset();
    session_destroy();
    header('Location: login.php?msg=login_required');
    exit;
}
$_SESSION['last_activity'] = $now; // refresh idle window on every guarded request

// ---- Stale-session check: does this user still exist in the DB? ----
// db.php uses require_once, so loading it here is safe even though
// the page also requires it afterwards.
require_once __DIR__ . '/db.php';

$user_id = (int) $_SESSION['user_id'];
$exists  = false;

try {
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $exists = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    // On any DB error, fail closed (treat as logged out) — never leak SQL.
    $exists = false;
}

// Row gone? Clear the session and force a fresh login so the page never
// tries to write a booking referencing a non-existent user.
if (!$exists) {
    $_SESSION = [];
    session_unset();
    session_destroy();
    header('Location: login.php?msg=login_required');
    exit;
}
