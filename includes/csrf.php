<?php
// includes/csrf.php
// CSRF helpers: one random token per session, verified in constant time.
//
// Viva points:
//  - Token lives in $_SESSION, so it is bound to the user's session and
//    regenerated automatically by a fresh login/session.
//  - random_bytes() is a CSPRNG — the token is not guessable.
//  - hash_equals() compares in constant time (no timing side channel).
//  - csrf_verify() must be called at the TOP of every POST handler,
//    BEFORE any state change (INSERT/UPDATE/session change).
require_once __DIR__ . '/session.php'; // guarantees the session is started

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 hex chars
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify()
{
    // is_string() guard (same style as the page handlers): ?csrf_token[]=x
    // sends an array, and hash_equals(array) is a TypeError on PHP 8.
    $sent  = $_POST['csrf_token'] ?? '';
    $sent  = is_string($sent) ? $sent : '';
    $known = $_SESSION['csrf_token'] ?? '';
    return $sent !== '' && hash_equals($known, $sent);
}
