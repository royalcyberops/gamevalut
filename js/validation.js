// js/validation.js — GameVault client-side form validation (vanilla JS).
// Loaded AFTER js/main.js on pages with forms (register/login/checkout).
// VIVA: JS validation is UX-only; PHP must re-validate everything
// server-side because users can bypass JS. Returning false from
// onsubmit blocks the form submit when data is invalid.

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- helpers: write/clear inline error messages ---------- */
  // VIVA: each input has a <span class="err" id="err-<fieldId>"> next to it.
  function showErr(id, msg) {
    var el = document.getElementById('err-' + id);
    if (el) el.textContent = msg;
  }
  function clearErr(id) {
    showErr(id, '');
  }

  /* ---------- regex rules (must match server-side PHP rules) ---------- */
  var RE = {
    name: /^[A-Za-z ]{3,}$/,          // letters+spaces, min 3 chars
    email: /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/, // standard email
    mobile: /^[6-9][0-9]{9}$/,        // Indian 10-digit, starts 6-9
    username: /^[A-Za-z0-9_]{4,15}$/, // 4-15 chars, letters/digits/_
    // min 8 chars, 1 lower + 1 upper + 1 digit + 1 special char
    password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/
  };

  // Shortcut: get trimmed value of an input by id ('' if missing).
  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value.trim() : '';
  }

  /* ---------------- REGISTER form ---------------- */
  // VIVA: checks fullName,email,mobile,username,password,confirm;
  // error spans: err-fullName, err-email, ... Returns true/false.
  function validateRegister() {
    var ok = true;

    var fullName = val('fullName');
    var email = val('email');
    var mobile = val('mobile');
    var username = val('username');
    var passEl = document.getElementById('password');
    var confEl = document.getElementById('confirm');
    var password = passEl ? passEl.value : ''; // don't trim passwords
    var confirm = confEl ? confEl.value : '';

    if (!RE.name.test(fullName)) {
      showErr('fullName', 'Enter your full name (letters, min 3).');
      ok = false;
    } else clearErr('fullName');

    if (!RE.email.test(email)) {
      showErr('email', 'Enter a valid email address.');
      ok = false;
    } else clearErr('email');

    if (!RE.mobile.test(mobile)) {
      showErr('mobile', 'Enter a 10-digit mobile starting 6-9.');
      ok = false;
    } else clearErr('mobile');

    if (!RE.username.test(username)) {
      showErr('username', '4-15 chars: letters, digits, underscore.');
      ok = false;
    } else clearErr('username');

    if (!RE.password.test(password)) {
      showErr('password', 'Min 8 chars with upper, lower, digit & symbol.');
      ok = false;
    } else clearErr('password');

    if (confirm === '' || confirm !== password) {
      showErr('confirm', 'Passwords do not match.');
      ok = false;
    } else clearErr('confirm');

    return ok;
  }

  var registerForm = document.getElementById('registerForm');
  if (registerForm) {
    // VIVA: returning false from onsubmit cancels the POST.
    registerForm.onsubmit = function () { return validateRegister(); };
  }

  /* ---------------- LOGIN form ---------------- */
  // VIVA: only non-empty checks here; real auth happens in PHP.
  function validateLogin() {
    var ok = true;

    var loginId = val('loginId');
    var passEl = document.getElementById('password');
    var password = passEl ? passEl.value : '';

    if (loginId === '') {
      showErr('loginId', 'Enter username or email.');
      ok = false;
    } else clearErr('loginId');

    if (password === '') {
      showErr('password', 'Enter your password.');
      ok = false;
    } else clearErr('password');

    return ok;
  }

  var loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.onsubmit = function () { return validateLogin(); };
  }

  /* ---------------- CHECKOUT form ---------------- */
  // VIVA: contactName letters min3, contactPhone mobile, address 10-300 chars,
  // deliveryDate today..+60 days, deliveryTime non-empty. Mirrors checkout.php.
  function validateCheckout() {
    var ok = true;

    var cName = val('contactName');
    var cPhone = val('contactPhone');
    var addrEl = document.getElementById('address');
    var addr = addrEl ? addrEl.value.trim() : '';
    var dateEl = document.getElementById('deliveryDate');
    var timeEl = document.getElementById('deliveryTime');
    var dateStr = dateEl ? dateEl.value : '';
    var timeStr = timeEl ? timeEl.value.trim() : '';

    if (!RE.name.test(cName)) {
      showErr('contactName', 'Enter contact name (letters, min 3).');
      ok = false;
    } else clearErr('contactName');

    if (!RE.mobile.test(cPhone)) {
      showErr('contactPhone', 'Enter a 10-digit mobile starting 6-9.');
      ok = false;
    } else clearErr('contactPhone');

    if (addr.length < 10 || addr.length > 300) {
      showErr('address', 'Address must be 10-300 characters.');
      ok = false;
    } else clearErr('address');

    if (dateStr === '') {
      showErr('deliveryDate', 'Pick a delivery date.');
      ok = false;
    } else {
      var picked = new Date(dateStr + 'T00:00:00');
      var today = new Date(); today.setHours(0, 0, 0, 0);
      var max = new Date(today); max.setDate(max.getDate() + 60);
      if (isNaN(picked.getTime()) || picked < today) {
        showErr('deliveryDate', 'Date cannot be in the past.');
        ok = false;
      } else if (picked > max) {
        showErr('deliveryDate', 'Date must be within 60 days.');
        ok = false;
      } else clearErr('deliveryDate');
    }

    if (timeStr === '') {
      showErr('deliveryTime', 'Pick a delivery time.');
      ok = false;
    } else clearErr('deliveryTime');

    return ok;
  }

  var checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) {
    checkoutForm.onsubmit = function () { return validateCheckout(); };
  }

  // NOTE: password show/hide (.toggle-pass) is handled in js/main.js
  // (loaded on every page via the footer). It must NOT be bound here too —
  // two listeners on the same button toggle twice per click (show then
  // instantly hide), which makes the button look dead.

  // Expose validators globally so inline onsubmit="return validateX()"
  // also works if a page uses inline handlers. VIVA: window.* makes
  // functions visible outside this DOMContentLoaded closure.
  window.showErr = showErr;
  window.clearErr = clearErr;
  window.validateRegister = validateRegister;
  window.validateLogin = validateLogin;
  window.validateCheckout = validateCheckout;
});
