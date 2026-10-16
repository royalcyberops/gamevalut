// js/main.js — GameVault shared interactivity (vanilla JS, no frameworks).
// Loaded on EVERY page via includes/footer.php, so each block below
// first checks that its elements exist before doing anything.

document.addEventListener('DOMContentLoaded', function () {

  /* ---------------------------------------------------------
   * 0. Toast notification system — showToast(type, message)
   * Appends a toast to #toastContainer (creates it if missing).
   * Auto-removes after 4 seconds; click X to dismiss.
   * --------------------------------------------------------- */
  function ensureToastContainer() {
    var el = document.getElementById('toastContainer');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toastContainer';
      el.className = 'toast-container';
      document.body.appendChild(el);
    }
    return el;
  }

  window.showToast = function (type, message) {
    var container = ensureToastContainer();
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || 'info');
    var iconMap = { success: '&#10004;', error: '&#10008;', info: '&#128172;' };
    toast.innerHTML =
      '<span class="toast-icon">' + (iconMap[type] || iconMap.info) + '</span>' +
      '<span class="toast-body">' + message + '</span>' +
      '<button class="toast-close" aria-label="Dismiss">&#215;</button>';
    container.appendChild(toast);

    var closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', function () {
      dismissToast(toast);
    });
    setTimeout(function () { dismissToast(toast); }, 4000);
  };

  function dismissToast(toast) {
    toast.classList.add('toast-exit');
    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 300);
  }

  /* ---------------------------------------------------------
   * 1. Mobile menu — #hamburger toggles .open on #navLinks
   * Closes on link press, on ESC, and when clicking outside.
   * --------------------------------------------------------- */
  var hamburger = document.getElementById('hamburger');
  var navLinks = document.getElementById('navLinks');

  if (hamburger && navLinks) {
    hamburger.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = navLinks.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close when a nav link is clicked.
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navLinks.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
      });
    });

    // Close on ESC.
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && navLinks.classList.contains('open')) {
        navLinks.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
      }
    });

    // Close when clicking outside the menu.
    document.addEventListener('click', function (e) {
      if (navLinks.classList.contains('open') &&
          !navLinks.contains(e.target) &&
          !hamburger.contains(e.target)) {
        navLinks.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------------------------------------------------------
   * 2. Hero carousel — .slides > .slide, #prevBtn/#nextBtn, #dots
   * Auto-slide 5s, pause on hover, wrap-around, touch swipe.
   * --------------------------------------------------------- */
  var slidesWrap = document.querySelector('.slides');
  var slides = document.querySelectorAll('.slides > .slide');
  var prevBtn = document.getElementById('prevBtn');
  var nextBtn = document.getElementById('nextBtn');
  var dotsBox = document.getElementById('dots');

  if (slidesWrap && slides.length > 0) {
    var current = 0;
    var timer = null;
    var DELAY = 5000;
    var hovering = false;

    function buildDots() {
      if (!dotsBox) return;
      dotsBox.innerHTML = '';
      slides.forEach(function (_, i) {
        var d = document.createElement('button');
        d.type = 'button';
        d.className = 'dot' + (i === current ? ' active' : '');
        d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
        d.addEventListener('click', function () {
          showSlide(i);
          restartAuto();
        });
        dotsBox.appendChild(d);
      });
    }

    function showSlide(i) {
      current = ((i % slides.length) + slides.length) % slides.length;
      slidesWrap.style.transform = 'translateX(-' + (current * 100) + '%)';
      slides.forEach(function (s, idx) {
        s.classList.toggle('active', idx === current);
      });
      if (dotsBox) {
        var dots = dotsBox.querySelectorAll('.dot');
        dots.forEach(function (d, idx) {
          d.classList.toggle('active', idx === current);
        });
      }
    }

    function startAuto() {
      stopAuto();
      timer = setInterval(function () { showSlide(current + 1); }, DELAY);
    }
    function stopAuto() {
      if (timer !== null) { clearInterval(timer); timer = null; }
    }
    function restartAuto() {
      stopAuto();
      if (!hovering) startAuto();
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () { showSlide(current - 1); restartAuto(); });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () { showSlide(current + 1); restartAuto(); });
    }

    var hoverZone = slidesWrap.closest('.hero') || slidesWrap;
    hoverZone.addEventListener('mouseenter', function () { hovering = true; stopAuto(); });
    hoverZone.addEventListener('mouseleave', function () { hovering = false; restartAuto(); });

    // Touch swipe support.
    var startX = 0, isDown = false;
    slidesWrap.addEventListener('touchstart', function (e) {
      isDown = true;
      startX = e.touches[0].clientX;
      stopAuto();
    }, { passive: true });
    slidesWrap.addEventListener('touchend', function (e) {
      if (!isDown) return;
      isDown = false;
      var endX = e.changedTouches[0].clientX;
      var diff = startX - endX;
      if (Math.abs(diff) > 40) {
        if (diff > 0) showSlide(current + 1); else showSlide(current - 1);
        restartAuto();
      }
    }, { passive: true });

    buildDots();
    showSlide(0);
    startAuto();
  }

  /* ---------------------------------------------------------
   * 3. Genre filter — .chip[data-genre] filters .game-card[data-genre]
   * --------------------------------------------------------- */
  var chips = document.querySelectorAll('.chip[data-genre]');
  var cards = document.querySelectorAll('.game-card[data-genre]');
  if (chips.length > 0 && cards.length > 0) {
    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        chips.forEach(function (c) {
          c.classList.remove('selected'); c.classList.remove('active');
        });
        chip.classList.add('selected');

        var g = (chip.getAttribute('data-genre') || 'All').toLowerCase();
        cards.forEach(function (card) {
          var cg = (card.getAttribute('data-genre') || '').toLowerCase();
          var show = (g === 'all') || (cg === g);
          card.style.display = show ? '' : 'none';
          // Reveal re-trigger for filtered cards
          if (show) card.classList.remove('reveal');
        });
      });
    });
  }

  /* ---------------------------------------------------------
   * 4. Booking live preview — #gameSelect, #qty, #totalPreview
   * --------------------------------------------------------- */
  var gameSelect = document.getElementById('gameSelect');
  var qtyInput = document.getElementById('qty');
  var totalPreview = document.getElementById('totalPreview');
  var pricePreview = document.getElementById('pricePreview');
  var qtyPreview = document.getElementById('qtyPreview');

  if (gameSelect && qtyInput && totalPreview) {
    function inr(n) {
      return '₹' + Number(n).toLocaleString('en-IN', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
      });
    }
    function updatePreview() {
      var opt = gameSelect.options[gameSelect.selectedIndex];
      var price = opt ? parseFloat(opt.getAttribute('data-price')) || 0 : 0;
      var disc = opt ? parseFloat(opt.getAttribute('data-discount')) || 0 : 0;
      var qty = parseInt(qtyInput.value, 10);
      if (isNaN(qty)) qty = 0;
      if (qty < 0) qty = 0;
      if (qty > 5) qty = 5;

      var discounted = price * (1 - disc / 100);
      var total = discounted * qty;

      if (pricePreview) {
        pricePreview.textContent = opt && opt.value
          ? inr(discounted) + (disc > 0 ? '  (-' + disc + '%)' : '')
          : '—';
      }
      if (qtyPreview) {
        qtyPreview.textContent = String(qty);
      }
      totalPreview.textContent = opt && opt.value ? inr(total) : '—';
    }
    gameSelect.addEventListener('change', updatePreview);
    qtyInput.addEventListener('input', updatePreview);
    qtyInput.addEventListener('change', updatePreview);
    updatePreview();
  }

  /* ---------------------------------------------------------
   * 5. Scroll reveal — elements with .reveal fade in on scroll
   * --------------------------------------------------------- */
  var revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length > 0) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    revealEls.forEach(function (el) { observer.observe(el); });
  }

  /* ---------------------------------------------------------
   * 6. Password show/hide toggle (data-target)
   * --------------------------------------------------------- */
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

  /* ---------------------------------------------------------
   * 7. Back to top button — shows after 300px scroll,
   *    smooth scroll to top on click.
   * --------------------------------------------------------- */
  var backToTop = document.getElementById('backToTop');
  if (backToTop) {
    var ticking = false;
    function updateBackToTop() {
      if (window.scrollY > 300) {
        backToTop.classList.add('visible');
      } else {
        backToTop.classList.remove('visible');
      }
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) {
        window.requestAnimationFrame(updateBackToTop);
        ticking = true;
      }
    });
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
});
