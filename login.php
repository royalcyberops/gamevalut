<?php
// login.php — GameVault login page.
// Viva points:
//  - Accepts username OR email in one field (bind same input twice).
//  - password_verify() against bcrypt hash.
//  - session_regenerate_id(true) prevents session fixation.
//  - Generic error message — never reveal if username or password was wrong.

require_once __DIR__ . '/includes/session.php'; // hardened bootstrap (the ONLY session_start)
require_once __DIR__ . '/includes/csrf.php';    // csrf_token() / csrf_verify()

// Already logged in? Skip login form.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php'; // gives $conn

$message   = '';
$msg_class = '';

// ---- Flash messages from redirects (logout / auth guard) ----
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'loggedout') {
        $message   = 'You have been logged out successfully.';
        $msg_class = 'msg-success';
    } elseif ($_GET['msg'] === 'login_required') {
        $message   = 'Please login first.';
        $msg_class = 'msg-error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    // CSRF check failed — reject before any database lookup or session change.
    $message   = 'Security check failed. Please reload the page and try again.';
    $msg_class = 'msg-error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // is_string() guards: ?loginId[]=x sends an array, and trim(array)
    // is a fatal TypeError on PHP 8. Empty string => validation error.
    $loginId  = is_string($_POST['loginId'] ?? '')  ? trim($_POST['loginId'])  : '';
    $password = is_string($_POST['password'] ?? '') ? $_POST['password'] : '';

    if ($loginId === '' || $password === '') {
        $message   = 'Please enter your username/email and password.';
        $msg_class = 'msg-error';
    } else {
        // Look up by username OR email. Same input bound to both placeholders.
        // db.php uses PDO ERRMODE_EXCEPTION, so wrap in try/catch: on any DB
        // error we fall through to the same generic failure message.
        $row = null;
        try {
            $stmt = $conn->prepare(
                'SELECT user_id, username, full_name, password, is_admin FROM users WHERE username = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$loginId, $loginId]);
            $row = $stmt->fetch() ?: null; // PDO fetch() returns false on no row
        } catch (PDOException $e) {
            $row = null; // fail closed, never leak SQL
        }

        if ($row !== null && password_verify($password, $row['password'])) {
            // Success: prevent session fixation, store identity, go to dashboard.
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int) $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['name']     = $row['full_name'];
            $_SESSION['is_admin'] = (int) ($row['is_admin'] ?? 0);
            // Track absolute lifetime + idle timeout (enforced in includes/auth.php).
            $_SESSION['login_at']      = time();
            $_SESSION['last_activity'] = time();
            header('Location: dashboard.php');
            exit;
        }
        // Generic failure message either way (user not found or wrong password).
        $message   = 'Invalid username or password.';
        $msg_class = 'msg-error';
    }
}

$page_title  = 'Login';
$active_page = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page royal-bg">
  <div class="auth-card auth-narrow royal-card">
    <div class="auth-crest royal-crest"><span>GV</span></div>
    <div class="auth-eyebrow">GameVault</div>
    <h2 class="auth-title">Welcome Back</h2>
    <p class="auth-subtitle">Sign in to your GameVault account</p>

    <?php if ($message !== ''): ?>
      <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form id="loginForm" method="post" action="login.php" onsubmit="return validateLogin()" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <div class="form-group">
        <label for="loginId">Username or Email</label>
        <input type="text" id="loginId" name="loginId" placeholder="e.g. gamer123 / you@example.com" autocomplete="username" required>
        <span class="err" id="err-loginId"></span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" autocomplete="current-password" required>
          <button type="button" class="toggle-pass" data-target="password">Show</button>
        </div>
        <span class="err" id="err-password"></span>
      </div>

      <button type="submit" class="btn btn-royal btn-block btn-shine">Login</button>
    </form>

    <p class="auth-links">New here? <a href="register.php">Create an account</a>.</p>
    <div class="auth-meta"><span>&#128274; Secured login</span><span>Members only</span></div>
  </div>
</div>

<script src="js/validation.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
