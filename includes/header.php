<?php
// includes/header.php
// Shared navbar. Expects optional $page_title and $active_page variables.
// Starts the session if needed and decides links from $_SESSION.
require_once __DIR__ . '/session.php'; // hardened bootstrap (the ONLY session_start)
require_once __DIR__ . '/csrf.php';    // csrf_token() for the logout form
require_once __DIR__ . '/cart.php';    // cart_count() for the nav badge
$active_page = $active_page ?? basename($_SERVER['PHP_SELF'], '.php');
$page_title  = $page_title ?? 'GameVault';
$is_logged   = isset($_SESSION['user_id']);
$is_admin    = $is_logged && (int) ($_SESSION['is_admin'] ?? 0) === 1;
$cart_n      = cart_count();
$hello_name  = $is_logged ? htmlspecialchars($_SESSION['username']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?> | GameVault</title>
<?php $css_v = @filemtime(__DIR__ . '/../css/style.css') ?: time(); ?>
<link rel="stylesheet" href="css/style.css?v=<?php echo (int)$css_v; ?>">
</head>
<body>
<a href="#main" class="skip-link">Skip to main content</a>
<header class="navbar">
  <div class="nav-inner">
    <!-- Original logo: CSS diamond + text (no Steam branding) -->
    <a href="index.php" class="logo">
      <span class="logo-mark">GV</span>
      <span class="logo-text">Game<strong>Vault</strong></span>
    </a>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">&#9776;</button>
    <nav class="nav-links" id="navLinks">
      <a href="index.php" class="<?php echo ($active_page === 'index') ? 'active' : ''; ?>">Home</a>
      <a href="categories.php" class="<?php echo ($active_page === 'categories') ? 'active' : ''; ?>">Categories</a>
      <?php if (!$is_logged): ?>
        <a href="register.php" class="<?php echo ($active_page === 'register') ? 'active' : ''; ?>">Register</a>
        <a href="login.php" class="<?php echo ($active_page === 'login') ? 'active' : ''; ?>">Login</a>
      <?php else: ?>
        <a href="cart.php" class="<?php echo ($active_page === 'cart') ? 'active' : ''; ?>">Cart<?php echo ($cart_n > 0) ? ' (' . (int) $cart_n . ')' : ''; ?></a>
        <a href="dashboard.php" class="<?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
        <?php if ($is_admin): ?>
          <a href="admin.php" class="<?php echo ($active_page === 'admin') ? 'active' : ''; ?>">Admin</a>
        <?php endif; ?>
        <!-- Logout is state-changing: POST + CSRF token (a plain link would be CSRF-able). -->
        <form method="post" action="logout.php" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
          <button type="submit" class="logout-btn">Logout</button>
        </form>
        <span class="hi-user">Hi, <?php echo $hello_name; ?></span>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="container" id="main" tabindex="-1">
