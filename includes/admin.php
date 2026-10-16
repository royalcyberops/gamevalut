<?php
// includes/admin.php
// Admin guard for admin.php. Extends the auth.php session guard with a role
// check: the logged-in user must have users.is_admin = 1 (re-read from the
// database on every request, so a demoted admin loses access immediately
// without needing to log out first).
//
// Usage: require_once __DIR__ . '/admin.php';  (at the very top)

require_once __DIR__ . '/auth.php'; // session guard + expiry + stale-user check
require_once __DIR__ . '/db.php';   // gives $conn (require_once: safe to load twice)

try {
    $stmt = $conn->prepare('SELECT is_admin FROM users WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['is_admin'] !== 1) {
        header('Location: dashboard.php?msg=forbidden');
        exit;
    }
} catch (PDOException $e) {
    // On any DB error, fail closed — never leak SQL, never grant admin.
    header('Location: dashboard.php?msg=forbidden');
    exit;
}
