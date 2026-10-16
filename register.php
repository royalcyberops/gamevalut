<?php
// register.php — GameVault registration page.
// Viva points:
//  - Server-side validation duplicates client-side JS validation.
//  - Duplicate email/username check before INSERT.
//  - password_hash() (bcrypt) — never store plain passwords.
//  - htmlspecialchars() when echoing back user input.

require_once __DIR__ . '/includes/session.php'; // hardened bootstrap (the ONLY session_start)
require_once __DIR__ . '/includes/csrf.php';    // csrf_token() / csrf_verify()

// Already logged in? No need to register again.
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php'; // gives $conn (PDO)

$message = '';   // HTML message shown above the form
$msg_class = ''; // msg-success | msg-error
$message_html = false; // true ONLY for our own hardcoded success link below

// Preserve entered values so user does not re-type after an error.
$fullName = '';
$email    = '';
$mobile   = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    // CSRF check failed — reject before any validation or INSERT.
    $message   = 'Security check failed. Please reload the page and try again.';
    $msg_class = 'msg-error';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim text inputs (passwords are NOT trimmed).
    // is_string() guards: ?fullName[]=x sends an array, and trim(array)
    // is a fatal TypeError on PHP 8. Empty string => validation error.
    // Single-read: the ?? in the check must not be re-read in the branch.
    $in_fn = $_POST['fullName'] ?? '';
    $in_em = $_POST['email'] ?? '';
    $in_mo = $_POST['mobile'] ?? '';
    $in_un = $_POST['username'] ?? '';
    $in_pw = $_POST['password'] ?? '';
    $in_cf = $_POST['confirm'] ?? '';
    $fullName = is_string($in_fn) ? trim($in_fn) : '';
    $email    = is_string($in_em) ? trim($in_em) : '';
    $mobile   = is_string($in_mo) ? trim($in_mo) : '';
    $username = is_string($in_un) ? trim($in_un) : '';
    $password = is_string($in_pw) ? $in_pw : '';
    $confirm  = is_string($in_cf) ? $in_cf : '';

    // ---- Server-side validation (mirrors js/validation.js) ----
    $error = '';
    if (!preg_match('/^[A-Za-z ]{3,100}$/', $fullName)) {
        $error = 'Full name must contain only letters/spaces, minimum 3 characters.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
        $error = 'Mobile must be 10 digits starting with 6-9.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{4,15}$/', $username)) {
        $error = 'Username must be 4-15 chars: letters, digits or underscore.';
    } elseif (strlen($password) < 8
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $error = 'Password must be min 8 chars with upper, lower, digit and special character.';
    } elseif ($password !== $confirm) {
        $error = 'Password and Confirm Password do not match.';
    }

    if ($error !== '') {
        // Validation failed — show error, keep typed values.
        $message   = $error;
        $msg_class = 'msg-error';
    } else {
        // ---- Check duplicates: email OR username already taken? ----
        // db.php enables PDO ERRMODE_EXCEPTION, so queries THROW on error
        // instead of returning false — always wrap them in try/catch.
        $taken    = false;
        $db_error = false;
        try {
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? OR username = ?');
            $stmt->execute([$email, $username]);
            // fetch() check (not rowCount()): rowCount on SELECT is
            // driver-dependent and unreliable on unbuffered drivers.
            $taken = ($stmt->fetch() !== false);
        } catch (PDOException $e) {
            $db_error = true; // fail closed, generic message below
        }

        if ($db_error) {
            $message   = 'Registration failed. Please try again.';
            $msg_class = 'msg-error';
        } elseif ($taken) {
            $message   = 'Email or username already registered. Try logging in.';
            $msg_class = 'msg-error';
        } else {
            // ---- Insert new user with hashed password ----
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $ins = $conn->prepare(
                    'INSERT INTO users (full_name, email, mobile, username, password) VALUES (?, ?, ?, ?, ?)'
                );
                $ins->execute([$fullName, $email, $mobile, $username, $hash]);
                $message   = 'Registration successful! <a href="login.php">Login here</a>.';
                $msg_class = 'msg-success';
                $message_html = true; // safe: hardcoded link, no user input inside
                // Clear preserved values after success.
                $fullName = $email = $mobile = $username = '';
            } catch (PDOException $e) {
                // 1062 = duplicate key (race: taken between our check and INSERT).
                // PDOException::getCode() returns the SQLSTATE ('23000'), so the
                // MySQL driver number must be read from errorInfo[1].
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $message   = 'Email or username already registered. Try logging in.';
                    $msg_class = 'msg-error';
                } else {
                    $message   = 'Registration failed. Please try again.';
                    $msg_class = 'msg-error';
                }
            }
        }
    }
}

$page_title  = 'Register';
$active_page = 'register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page royal-bg">
  <div class="auth-card royal-card">
    <div class="auth-crest royal-crest"><span>GV</span></div>
    <div class="auth-eyebrow">GameVault</div>
    <h2 class="auth-title">Create Account</h2>
    <p class="auth-subtitle">Join GameVault to reserve games</p>

    <?php if ($message !== ''): ?>
      <?php if ($message_html): ?>
      <!-- Success link is our own hardcoded HTML (no user input), so show as-is -->
      <p class="<?php echo $msg_class; ?>"><?php echo $message; ?></p>
      <?php else: ?>
      <p class="<?php echo $msg_class; ?>"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>
    <?php endif; ?>

    <form id="registerForm" method="post" action="register.php" onsubmit="return validateRegister()" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <div class="form-group">
        <label for="fullName">Full Name</label>
        <input type="text" id="fullName" name="fullName" maxlength="100" placeholder="e.g. Aarav Sharma" value="<?php echo htmlspecialchars($fullName); ?>" autocomplete="name" required>
        <span class="err" id="err-fullName"></span>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email" required>
        <span class="err" id="err-email"></span>
      </div>

      <div class="form-group">
        <label for="mobile">Mobile (10 digits)</label>
        <input type="text" id="mobile" name="mobile" maxlength="10" inputmode="numeric" placeholder="98765 43210" value="<?php echo htmlspecialchars($mobile); ?>" autocomplete="tel" required>
        <span class="err" id="err-mobile"></span>
      </div>

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="4-15 chars, letters/numbers/_" value="<?php echo htmlspecialchars($username); ?>" autocomplete="username" required>
        <span class="err" id="err-username"></span>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrap">
          <input type="password" id="password" name="password" placeholder="Min 8 chars, Aa1@" autocomplete="new-password" required>
          <button type="button" class="toggle-pass" data-target="password">Show</button>
        </div>
        <span class="err" id="err-password"></span>
      </div>

      <div class="form-group">
        <label for="confirm">Confirm Password</label>
        <div class="password-wrap">
          <input type="password" id="confirm" name="confirm" placeholder="Repeat your password" autocomplete="new-password" required>
          <button type="button" class="toggle-pass" data-target="confirm">Show</button>
        </div>
        <span class="err" id="err-confirm"></span>
      </div>

      <button type="submit" class="btn btn-royal btn-block btn-shine">Register</button>
    </form>

    <p class="auth-links">Already have an account? <a href="login.php">Login here</a>.</p>
    <div class="auth-meta"><span>&#128274; Safe &amp; secured</span><span>Free signup</span></div>
  </div>
</div>

<?php $val_v = @filemtime(__DIR__ . '/js/validation.js') ?: time(); ?>
<script src="js/validation.js?v=<?php echo (int)$val_v; ?>"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
