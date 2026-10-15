<?php
// logout.php — GameVault logout.
// Viva points:
//  - session_unset() + session_destroy() clears server-side session data.
//  - Deleting the session cookie removes the client-side session id.
//  - Redirect with ?msg=loggedout shows a green confirmation on login.php.

require_once __DIR__ . '/includes/session.php'; // hardened bootstrap (the ONLY session_start)
require_once __DIR__ . '/includes/csrf.php';    // csrf_verify()

// Logout must be a POST carrying a valid CSRF token (logout.php?x would
// otherwise allow forced logout via e.g. an <img src> tag).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    header('Location: login.php');
    exit;
}

// Clear all session variables.
$_SESSION = array();
session_unset();
session_destroy();

// Delete the session cookie (if cookies are used for sessions).
// Use the real session cookie params, otherwise a cookie set with a
// non-default path/domain would NOT be deleted by this call.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,            // expire in the past
        $params['path'],          // must match the original cookie
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Back to login page with a success flag.
header('Location: login.php?msg=loggedout');
exit;
