<?php
// includes/footer.php
// Shared footer. Closes <main> opened in header.php
// and loads js/main.js on every page.
?>
</main>
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-col">
      <div class="logo footer-logo" style="margin-bottom:14px;"><span class="logo-mark">GV</span> <span class="logo-text">Game<strong>Vault</strong></span></div>
      <p class="footer-desc">An original college mini-project. A Steam-inspired game store demo with no affiliated branding.</p>
      <div class="footer-social">
        <a href="#" class="footer-social-link" aria-label="Discord">&#128179;</a>
        <a href="#" class="footer-social-link" aria-label="Twitter">&#128178;</a>
        <a href="#" class="footer-social-link" aria-label="Email">&#128274;</a>
      </div>
    </div>
    <div class="footer-col">
      <h4>Store</h4>
      <a href="index.php">Home</a>
      <a href="booking.php">Reserve a Game</a>
      <a href="dashboard.php">My Dashboard</a>
    </div>
    <div class="footer-col">
      <h4>Account</h4>
      <a href="register.php">Register</a>
      <a href="login.php">Login</a>
      <!-- Logout is state-changing: POST + CSRF token (a plain link would be CSRF-able). -->
      <form method="post" action="logout.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <button type="submit" class="logout-btn">Logout</button>
      </form>
    </div>
  </div>
  <div class="footer-bottom">&copy; <?php echo date('Y'); ?> GameVault &mdash; Built with HTML, CSS, JS, PHP &amp; MySQL for academic use.</div>
</footer>

<!-- Toast notification container (site-wide) -->
<div id="toastContainer" class="toast-container"></div>

<!-- Back to top button -->
<button id="backToTop" class="btn back-to-top" aria-label="Back to top">&u2191;</button>

<script src="js/main.js"></script>
</body>
</html>
