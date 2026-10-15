// js/validation.js — GameVault client-side form validation (vanilla JS).
// Loaded AFTER js/main.js on pages with forms (register/login/booking).
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

  /* ---------------- BOOKING form ---------------- */
  // VIVA: gameSelect non-empty, qty 1-5, bookingDate today..+60 days,
  // bookingTime non-empty, contactName letters min3, contactPhone mobile.
  function validateBooking() {
    var ok = true;

    var gameSelect = document.getElementById('gameSelect');
    var qtyEl = document.getElementById('qty');
    var dateEl = document.getElementById('bookingDate');
    var timeEl = document.getElementById('bookingTime');

    var gameVal = gameSelect ? String(gameSelect.value).trim() : '';
    var qty = qtyEl ? parseInt(qtyEl.value, 10) : NaN;
    var dateStr = dateEl ? dateEl.value : '';
    var timeStr = timeEl ? timeEl.value.trim() : '';
    var cName = val('contactName');
    var cPhone = val('contactPhone');

    if (gameVal === '') {
      showErr('gameSelect', 'Please choose a game.');
      ok = false;
    } else clearErr('gameSelect');

    // qty must be an integer 1-5 (matches server rule).
    if (isNaN(qty) || qty < 1 || qty > 5) {
      showErr('qty', 'Quantity must be 1 to 5.');
      ok = false;
    } else clearErr('qty');

    if (dateStr === '') {
      showErr('bookingDate', 'Pick a date.');
      ok = false;
    } else {
      // Compare date-only (strip time) to avoid timezone edge cases.
      var picked = new Date(dateStr + 'T00:00:00');
      var today = new Date(); today.setHours(0, 0, 0, 0);
      var max = new Date(today); max.setDate(max.getDate() + 60);
      if (isNaN(picked.getTime()) || picked < today) {
        showErr('bookingDate', 'Date cannot be in the past.');
        ok = false;
      } else if (picked > max) {
        showErr('bookingDate', 'Date must be within 60 days.');
        ok = false;
      } else clearErr('bookingDate');
    }

    if (timeStr === '') {
      showErr('bookingTime', 'Pick a time slot.');
      ok = false;
    } else clearErr('bookingTime');

    if (!RE.name.test(cName)) {
      showErr('contactName', 'Enter contact name (letters, min 3).');
      ok = false;
    } else clearErr('contactName');

    if (!RE.mobile.test(cPhone)) {
      showErr('contactPhone', 'Enter a 10-digit mobile starting 6-9.');
      ok = false;
    } else clearErr('contactPhone');

    return ok;
  }

  var bookingForm = document.getElementById('bookingForm');
  if (bookingForm) {
    bookingForm.onsubmit = function () { return validateBooking(); };
  }

  /* ---------------- Password show/hide toggle ---------------- */
  // VIVA: buttons with class .toggle-pass and data-target="<input id>"
  // flip the input type between password and text, and swap the icon.
  var toggles = document.querySelectorAll('.toggle-pass[data-target]');
  toggles.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-target'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });

  // Expose validators globally so inline onsubmit="return validateX()"
  // also works if a page uses inline handlers. VIVA: window.* makes
  // functions visible outside this DOMContentLoaded closure.
  window.showErr = showErr;
  window.clearErr = clearErr;
  window.validateRegister = validateRegister;
  window.validateLogin = validateLogin;
  window.validateBooking = validateBooking;
});
