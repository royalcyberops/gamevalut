// js/main.js — GameVault shared interactivity (vanilla JS, no frameworks).
// Loaded on EVERY page via includes/footer.php, so each block below
// first checks that its elements exist before doing anything.

document.addEventListener('DOMContentLoaded', function () {

  // Progressive-enhancement flag: CSS entrance states (.reveal, hero text)
  // are scoped under .js so content stays visible if this script fails.
  document.documentElement.classList.add('js');

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
    // Icons are a fixed internal map (safe for innerHTML); the message is
    // user-influenced, so it is set via textContent (never innerHTML) to
    // prevent XSS if a caller passes untrusted data.
    var iconMap = { success: '&#10004;', error: '&#10008;', info: '&#128172;' };
    var icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.innerHTML = iconMap[type] || iconMap.info;
    var body = document.createElement('span');
    body.className = 'toast-body';
    body.textContent = message;
    var closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.innerHTML = '&#215;';
    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(closeBtn);
    container.appendChild(toast);

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
   * #pauseBtn toggles autoplay; ArrowLeft/Right on hero region.
   * --------------------------------------------------------- */
  var slidesWrap = document.querySelector('.slides');
  var slides = document.querySelectorAll('.slides > .slide');
  var prevBtn = document.getElementById('prevBtn');
  var nextBtn = document.getElementById('nextBtn');
  var dotsBox = document.getElementById('dots');
  var pauseBtn = document.getElementById('pauseBtn');

  if (slidesWrap && slides.length > 0) {
    var current = 0;
    var timer = null;
    var DELAY = 5000;
    var hovering = false;
    var paused = false;
    var reduceMotionCarousel = window.matchMedia &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotionCarousel) paused = true;

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
          syncPauseBtn();
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
      if (!hovering && !paused) startAuto();
    }

    function syncPauseBtn() {
      if (!pauseBtn) return;
      var isPaused = (timer === null);
      pauseBtn.textContent = isPaused ? 'Play' : 'Pause';
      pauseBtn.setAttribute('aria-pressed', isPaused ? 'true' : 'false');
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', function () { showSlide(current - 1); restartAuto(); syncPauseBtn(); });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () { showSlide(current + 1); restartAuto(); syncPauseBtn(); });
    }

    var hoverZone = slidesWrap.closest('.hero') || slidesWrap;
    hoverZone.addEventListener('mouseenter', function () { hovering = true; stopAuto(); syncPauseBtn(); });
    hoverZone.addEventListener('mouseleave', function () { hovering = false; restartAuto(); syncPauseBtn(); });

    // Pause/play toggle button (if present). Pause is a control, always wired.
    if (pauseBtn) {
      pauseBtn.setAttribute('aria-pressed', paused ? 'true' : 'false');
      pauseBtn.addEventListener('click', function () {
        paused = !paused;
        if (paused) stopAuto(); else restartAuto();
        syncPauseBtn();
      });
    }

    // Keyboard: ArrowLeft/ArrowRight on the hero region.
    hoverZone.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        showSlide(current - 1);
        restartAuto();
        syncPauseBtn();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        showSlide(current + 1);
        restartAuto();
        syncPauseBtn();
      }
    });

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
        syncPauseBtn();
      }
    }, { passive: true });

    buildDots();
    showSlide(0);
    if (!paused) startAuto();
    syncPauseBtn();
  }

  /* ---------------------------------------------------------
   * 3. Genre filter — .chip[data-genre] filters .game-card[data-genre]
   * Per-grid empty states: toggles .empty-note[data-empty] and
   * hides the orphaned h2.section-title when its grid is empty.
   * --------------------------------------------------------- */
  var chips = document.querySelectorAll('.chip[data-genre]');
  var cards = document.querySelectorAll('.game-card[data-genre]');

  // Count visible cards in a grid; toggle its note + header. All guarded.
  function updateGridEmptyState(gridId, emptyKey) {
    var grid = document.getElementById(gridId);
    if (!grid) return;
    var gridCards = grid.querySelectorAll('.game-card');
    if (gridCards.length === 0) return;
    var visible = 0;
    gridCards.forEach(function (card) {
      if (card.style.display !== 'none') visible++;
    });
    var note = document.querySelector('.empty-note[data-empty="' + emptyKey + '"]');
    if (note) note.hidden = (visible !== 0);
    // Nearest preceding h2.section-title (walk previous siblings).
    var header = null;
    var sib = grid.previousElementSibling;
    while (sib) {
      if (sib.matches && sib.matches('h2.section-title')) { header = sib; break; }
      if (sib.querySelector) {
        var inner = sib.querySelector('h2.section-title');
        if (inner) { header = inner; break; }
      }
      sib = sib.previousElementSibling;
    }
    if (header) header.style.display = (visible === 0) ? 'none' : '';
  }

  function updateEmptyStates() {
    updateGridEmptyState('featuredGrid', 'featured');
    updateGridEmptyState('offersGrid', 'offers');
  }

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
        updateEmptyStates();
      });
    });
    updateEmptyStates();
  }

  /* ---------------------------------------------------------
   * 4. Stagger grid entrances — children of card grids reveal one
   *    after another via --d (consumed by .reveal in style.css).
   *    Must run BEFORE the reveal observer below observes them.
   * --------------------------------------------------------- */
  document.querySelectorAll('.games-grid, .categories-grid, .stat-row').forEach(function (grid) {
    Array.prototype.forEach.call(grid.children, function (child, i) {
      child.classList.add('reveal');
      child.style.setProperty('--d', ((i % 8) * 70) + 'ms');
    });
  });

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

  /* ---------------------------------------------------------
   * 8. Stat count-up — .stat-num counts from 0 on first view.
   *    Parses prefix (₹), number (commas/decimals preserved),
   *    suffix. Reduced-motion users get the final value instantly.
   * --------------------------------------------------------- */
  var reduceMotion = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.querySelectorAll('.stat-num').forEach(function (el) {
    var text = el.textContent || '';
    var m = text.match(/^([^0-9]*)([0-9,]+(?:\.[0-9]+)?)([\s\S]*)$/);
    if (!m) return; // non-numeric stat (e.g. badge text) — leave as-is
    var prefix = m[1], suffix = m[3];
    var target = parseFloat(m[2].replace(/,/g, ''));
    var decimals = (m[2].indexOf('.') >= 0) ? m[2].split('.')[1].length : 0;
    if (isNaN(target)) return;

    function render(v) {
      el.textContent = prefix +
        v.toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) +
        suffix;
    }
    if (reduceMotion) { render(target); return; }

    var done = false;
    var counter = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting || done) return;
        done = true;
        var start = null, DUR = 900;
        function tick(now) {
          if (start === null) start = now;
          var p = Math.min((now - start) / DUR, 1);
          var eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
          render(target * eased);
          if (p < 1) window.requestAnimationFrame(tick);
          else render(target); // snap exact (no float dust)
        }
        window.requestAnimationFrame(tick);
        counter.unobserve(entry.target);
      });
    }, { threshold: 0.4 });
    counter.observe(el);
  });

  /* ---------------------------------------------------------
   * 9. Cart badge pulse — after "Added to cart", the nav Cart
   *     link pulses once so the success is visible site-wide.
   * --------------------------------------------------------- */
  if (document.querySelector('.msg-success')) {
    var cartLink = document.querySelector('.nav-links a[href="cart.php"]');
    if (cartLink) {
      cartLink.classList.add('pulse');
      setTimeout(function () { cartLink.classList.remove('pulse'); }, 700);
    }
  }
});
