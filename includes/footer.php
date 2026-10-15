<?php
// includes/footer.php
// Shared footer. Closes <main> opened in header.php
// and loads js/main.js on every page.
?>
</main>
<footer class="footer">
  <div class="footer-inner">
    <div class="footer-col" style="flex: 1 1 260px;">
      <div class="logo" style="margin-bottom:12px;"><span class="logo-mark">GV</span> <span class="logo-text">Game<strong>Vault</strong></span></div>
      <p style="margin-bottom:14px;">An original college mini-project. A Steam-inspired game store demo with no affiliated branding.</p>
      <div style="display:flex; gap:10px;">
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:var(--panel);border:1px solid var(--border);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .2s;">&#128179;</a>
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:var(--panel);border:1px solid var(--border);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .2s;">&#128178;</a>
        <a href="#" style="width:36px;height:36px;border-radius:8px;background:var(--panel);border:1px solid var(--border);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .2s;">&#128274;</a>
      </div>
    </div>
    <div class="footer-col" style="flex: 1 1 180px;">
      <h4>Store</h4>
      <a href="index.php">Home</a>
      <a href="booking.php">Reserve a Game</a>
      <a href="dashboard.php">My Dashboard</a>
    </div>
    <div class="footer-col" style="flex: 1 1 180px;">
      <h4>Account</h4>
      <a href="register.php">Register</a>
      <a href="login.php">Login</a>
      <!-- Logout is state-changing: POST + CSRF token (a plain link would be CSRF-able). -->
      <form method="post" action="logout.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <button type="submit" class="btn btn-ghost btn-block" style="padding:8px 14px; font-size:0.85rem; background:none; border:none; color:var(--muted);">Logout</button>
      </form>
    </div>
  </div>
  <div class="footer-bottom">&copy; <?php echo date('Y'); ?> GameVault &mdash; Built with HTML, CSS, JS, PHP &amp; MySQL for academic use.</div>
</footer>
<script src="js/main.js"></script>
</body>
</html>
