<?php
// includes/session.php
// Single hardened session bootstrap for the whole site.
// Every page must require THIS file — never call session_start() directly —
// so the cookie/ini hardening below always runs BEFORE the session starts.
//
// Viva points:
//  - session.use_strict_mode: server refuses attacker-supplied session ids
//    (kills session fixation via a crafted PHPSESSID cookie).
//  - cookie_httponly: JavaScript cannot read the session cookie (anti XSS-theft).
//  - cookie_samesite Lax: cookie is not sent on cross-site POSTs (anti CSRF).
//  - cookie_secure: ONLY over real HTTPS. The site runs on plain
//    http://localhost, where a Secure cookie would be rejected outright and
//    break every login — so this must be conditional, never unconditional.
//  - session.use_only_cookies: session id is never accepted from the URL.

if (session_status() === PHP_SESSION_ACTIVE) {
    return; // already bootstrapped elsewhere in this request
}

// Detect real HTTPS (direct, or behind a reverse proxy using X-Forwarded-Proto).
$is_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
    'lifetime' => 0,        // cookie dies with the browser session
    'path'     => '/',
    'domain'   => '',
    'secure'   => $is_https, // 0 on plain http://localhost (1 would break sessions)
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
