/**
 * Add to Home Screen Pro — front-end engine
 * App-store style install sheet + per-browser guided instructions.
 */
(function () {
  'use strict';

  var D = window.A2HSP || {};
  var T = D.txt || {};
  // Element visibility toggles from admin panel (default: everything on)
  var SHOW = D.show || {};
  function shown(key) {
    return SHOW[key] === undefined || !!SHOW[key];
  }

  /* ================= Head fixes (theme color / viewport) ================= */

  /*
   * Themes often print their own <meta name="theme-color">, and browsers
   * honor only one of them — force every instance to the plugin color so
   * the browser bar matches the admin setting.
   */
  if (D.themeColor) {
    var tcMetas = document.querySelectorAll('meta[name="theme-color"]');
    if (tcMetas.length) {
      for (var ti = 0; ti < tcMetas.length; ti++) {
        tcMetas[ti].setAttribute('content', D.themeColor);
      }
    } else {
      var tc = document.createElement('meta');
      tc.name = 'theme-color';
      tc.content = D.themeColor;
      document.head.appendChild(tc);
    }
  }

  /*
   * env(safe-area-inset-top) — used to paint the iOS standalone status
   * bar — only reports real values with viewport-fit=cover.
   */
  (function () {
    var vp = document.querySelector('meta[name="viewport"]');
    if (vp) {
      var c = vp.getAttribute('content') || '';
      if (!/viewport-fit/i.test(c)) {
        vp.setAttribute('content', c ? c + ', viewport-fit=cover' : 'width=device-width, initial-scale=1, viewport-fit=cover');
      }
    } else {
      vp = document.createElement('meta');
      vp.name = 'viewport';
      vp.content = 'width=device-width, initial-scale=1, viewport-fit=cover';
      document.head.appendChild(vp);
    }
  })();

  /* ================= Service worker ================= */
  if ('serviceWorker' in navigator && D.swUrl) {
    window.addEventListener('load', function () {
      navigator.serviceWorker
        .register(D.swUrl, { scope: D.swScope || '/' })
        .catch(function (e) { console.warn('[A2HSP] SW failed:', e); });
    });
  }

  /* ================= Environment detection ================= */
  var ua = navigator.userAgent || '';

  var env = (function () {
    var iPadOS = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    var iOS = /iPhone|iPad|iPod/i.test(ua) || iPadOS;
    var android = /Android/i.test(ua);
    var inApp = /Instagram|FBAN|FBAV|FB_IAB|FBIOS|Twitter|TwitterAndroid|Telegram|WhatsApp|Line\/|MicroMessenger|GSA\//i.test(ua);
    return {
      iOS: iOS,
      iPad: /iPad/i.test(ua) || iPadOS,
      android: android,
      mobile: iOS || android,
      inApp: inApp,
      iosSafari: iOS && !inApp && /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS|Brave/i.test(ua),
      iosChrome: iOS && /CriOS/i.test(ua),
      iosFirefox: iOS && /FxiOS/i.test(ua),
      samsung: /SamsungBrowser/i.test(ua),
      firefox: !iOS && /Firefox/i.test(ua),
      opera: /OPR\//i.test(ua),
      chromium: !!window.chrome && !/Firefox|FxiOS/i.test(ua),
      macSafari: !iOS && !android && /Safari/i.test(ua) && !/Chrome|Chromium|Edg|OPR/i.test(ua)
    };
  })();

  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: minimal-ui)').matches ||
      window.navigator.standalone === true
    );
  }

  /* ================= Storage / frequency capping ================= */
  var store = {
    get: function (k) {
      try { return localStorage.getItem('a2hsp_' + k); } catch (e) { return null; }
    },
    set: function (k, v) {
      try { localStorage.setItem('a2hsp_' + k, String(v)); } catch (e) {}
    }
  };

  function dismissedRecently() {
    var t = parseInt(store.get('dismissed'), 10);
    if (!t) return false;
    return (Date.now() - t) / 86400000 < (D.dismissDays || 7);
  }

  function canAutoShow() {
    if (isStandalone() || store.get('installed')) return false;
    if (!env.mobile && !D.desktop) return false;
    if (dismissedRecently()) return false;
    var count = parseInt(store.get('count'), 10) || 0;
    if (D.maxDisplay > 0 && count >= D.maxDisplay) return false;
    var visits = (parseInt(store.get('visits'), 10) || 0) + 1;
    store.set('visits', visits);
    if (visits <= (D.minVisits || 0)) return false;
    return true;
  }

  /* ================= SVG icons ================= */
  var SVG = {
    share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13"/><path d="m8 7 4-4 4 4"/><path d="M5 11v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/></svg>',
    plusSquare: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M12 8v8M8 12h8"/></svg>',
    menu: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>',
    check: '<svg viewBox="0 0 52 52"><circle class="a2hsp-check-circle" cx="26" cy="26" r="24" fill="none"/><path class="a2hsp-check-mark" fill="none" d="M14 27l8 8 16-16"/></svg>',
    bolt: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>',
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>',
    wifiOff: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/></svg>',
    copy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>',
    compass: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5z"/></svg>'
  };

  var featureIcons = [SVG.home, SVG.bolt, SVG.wifiOff];

  /* ================= Deferred native prompt ================= */
  var deferredPrompt = null;
  var installAvailable = false;

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    installAvailable = true;
    if (pendingAutoShow) {
      pendingAutoShow = false;
      openSheet();
    }
  });

  window.addEventListener('appinstalled', function () {
    store.set('installed', '1');
    showSuccess();
  });

  /* ================= Flow decision ================= */
  function getFlow() {
    if (env.inApp) return 'inapp';
    if (env.iosSafari) return 'ios-safari';
    if (env.iosChrome) return 'ios-chrome';
    if (env.iosFirefox) return 'ios-firefox';
    if (env.iOS) return 'ios-safari';
    if (deferredPrompt) return 'native';
    if (env.samsung) return 'samsung';
    if (env.firefox && env.android) return 'firefox-android';
    if (env.macSafari) return 'mac-safari';
    if (env.chromium) return 'native';
    return 'generic';
  }

  /* ================= DOM builders ================= */
  var root = null;

  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html != null) n.innerHTML = html;
    return n;
  }

  function buildFeatures() {
    var feats = T.features || [];
    if (!feats.length) return null;
    var list = el('div', 'a2hsp-features');
    feats.slice(0, 3).forEach(function (f, i) {
      var item = el('div', 'a2hsp-feature');
      item.appendChild(el('span', 'a2hsp-feature-ic', featureIcons[i % featureIcons.length]));
      item.appendChild(el('span', 'a2hsp-feature-tx', escapeHtml(f)));
      list.appendChild(item);
    });
    return list;
  }

  function buildScreenshots() {
    var shots = D.screenshots || [];
    if (!shots.length) return null;
    var wrap = el('div', 'a2hsp-shots');
    shots.forEach(function (src) {
      var img = document.createElement('img');
      img.src = src;
      img.alt = '';
      img.loading = 'lazy';
      img.className = 'a2hsp-shot';
      wrap.appendChild(img);
    });
    return wrap;
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function stepRow(num, html) {
    var row = el('div', 'a2hsp-step');
    row.appendChild(el('span', 'a2hsp-step-num', String(num)));
    row.appendChild(el('div', 'a2hsp-step-body', html));
    return row;
  }

  /**
   * Guided instructions panel per flow (shown inside the sheet after CTA,
   * or directly for browsers with no native prompt).
   */
  function buildGuide(flow) {
    var g = el('div', 'a2hsp-guide');
    g.appendChild(el('div', 'a2hsp-guide-title', escapeHtml(T.guideTitle || 'راهنمای نصب')));

    if (flow === 'ios-safari') {
      g.appendChild(stepRow(1, escapeHtml(T.iosStep1) + ' <span class="a2hsp-inline-ic a2hsp-ic-share">' + SVG.share + '</span>'));
      g.appendChild(stepRow(2, escapeHtml(T.iosStep2) + ' <span class="a2hsp-inline-ic">' + SVG.plusSquare + '</span>'));
      g.appendChild(stepRow(3, escapeHtml(T.iosStep3)));
      // The arrow is appended to the overlay root (not the transformed panel)
      // so position:fixed anchors it to the real viewport edge, pointing at
      // Safari's share button.
      var arrow = el('div', 'a2hsp-arrow' + (env.iPad ? ' a2hsp-arrow-top' : ''), '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m5 12 7 7 7-7"/></svg>');
      if (root) {
        root.appendChild(arrow);
        if (!env.iPad) root.classList.add('a2hsp-has-arrow');
      } else {
        g.appendChild(arrow);
      }
    } else if (flow === 'ios-chrome') {
      g.appendChild(stepRow(1, 'روی آیکون «اشتراک‌گذاری» <span class="a2hsp-inline-ic a2hsp-ic-share">' + SVG.share + '</span> در نوار آدرس (بالا) بزنید'));
      g.appendChild(stepRow(2, escapeHtml(T.iosStep2) + ' <span class="a2hsp-inline-ic">' + SVG.plusSquare + '</span>'));
      g.appendChild(stepRow(3, escapeHtml(T.iosStep3)));
    } else if (flow === 'ios-firefox') {
      g.appendChild(stepRow(1, 'روی منوی <span class="a2hsp-inline-ic">' + SVG.menu + '</span> پایین صفحه بزنید'));
      g.appendChild(stepRow(2, 'گزینه «Share» و سپس «Add to Home Screen» را انتخاب کنید'));
    } else if (flow === 'samsung') {
      g.appendChild(stepRow(1, 'روی منوی <span class="a2hsp-inline-ic">' + SVG.menu + '</span> پایین صفحه بزنید'));
      g.appendChild(stepRow(2, 'گزینه «Add page to» را انتخاب کنید'));
      g.appendChild(stepRow(3, 'سپس «Home screen» را بزنید'));
    } else if (flow === 'firefox-android') {
      g.appendChild(stepRow(1, 'روی منوی <span class="a2hsp-inline-ic">' + SVG.menu + '</span> بالای صفحه بزنید'));
      g.appendChild(stepRow(2, 'گزینه «Install» یا «افزودن به صفحه اصلی» را انتخاب کنید'));
    } else if (flow === 'mac-safari') {
      g.appendChild(stepRow(1, 'از نوار ابزار Safari روی دکمه «اشتراک‌گذاری» <span class="a2hsp-inline-ic a2hsp-ic-share">' + SVG.share + '</span> کلیک کنید'));
      g.appendChild(stepRow(2, 'گزینه «Add to Dock» را انتخاب کنید'));
    } else if (flow === 'inapp') {
      g.appendChild(stepRow(1, escapeHtml(T.inappHint)));
      var copyBtn = el('button', 'a2hsp-copy-btn', '<span class="a2hsp-inline-ic">' + SVG.copy + '</span> ' + escapeHtml(T.copyLink));
      copyBtn.type = 'button';
      copyBtn.addEventListener('click', function () {
        var url = window.location.href;
        var done = function () {
          copyBtn.innerHTML = escapeHtml(T.copied);
          setTimeout(function () {
            copyBtn.innerHTML = '<span class="a2hsp-inline-ic">' + SVG.copy + '</span> ' + escapeHtml(T.copyLink);
          }, 2000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(done, done);
        } else {
          var ta = document.createElement('textarea');
          ta.value = url;
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(ta);
          done();
        }
      });
      var wrap = el('div', 'a2hsp-copy-wrap');
      wrap.appendChild(copyBtn);
      g.appendChild(wrap);
    } else {
      g.appendChild(stepRow(1, 'در نوار آدرس مرورگر، روی آیکون نصب <span class="a2hsp-inline-ic">' + SVG.plusSquare + '</span> کلیک کنید'));
      g.appendChild(stepRow(2, 'روی «Install» بزنید'));
    }
    return g;
  }

  /* ================= Sheet (main popup) ================= */
  function buildSheet() {
    var flow = getFlow();

    root = el('div', 'a2hsp-root a2hsp-' + (D.style === 'fullscreen' ? 'fullscreen' : 'sheet'));
    root.dir = D.dir || 'rtl';
    if (D.darkMode === 'dark') root.classList.add('a2hsp-dark');
    if (D.darkMode === 'light') root.classList.add('a2hsp-light');
    root.style.setProperty('--a2hsp-accent', D.accent || '#4f46e5');

    var backdrop = el('div', 'a2hsp-backdrop');
    backdrop.addEventListener('click', dismiss);
    root.appendChild(backdrop);

    var sheet = el('div', 'a2hsp-panel');
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.setAttribute('aria-label', T.title || '');

    // drag handle
    sheet.appendChild(el('div', 'a2hsp-handle'));

    // close button
    var close = el('button', 'a2hsp-close', '&times;');
    close.type = 'button';
    close.setAttribute('aria-label', 'بستن');
    close.addEventListener('click', dismiss);
    sheet.appendChild(close);

    // ---- app header (store style) ----
    var head = el('div', 'a2hsp-head');
    if (D.icon) {
      var ic = el('div', 'a2hsp-icon');
      var img = document.createElement('img');
      img.src = D.icon;
      img.alt = '';
      ic.appendChild(img);
      head.appendChild(ic);
    }
    var meta = el('div', 'a2hsp-meta');
    meta.appendChild(el('div', 'a2hsp-name', escapeHtml(D.appName)));
    if (shown('host') && D.host) {
      meta.appendChild(el('div', 'a2hsp-host', escapeHtml(D.host)));
    }
    if (shown('subtitle') && T.subtitle) {
      meta.appendChild(el('div', 'a2hsp-sub', escapeHtml(T.subtitle)));
    }
    head.appendChild(meta);
    sheet.appendChild(head);

    // ---- description ----
    if (shown('description') && D.description) {
      sheet.appendChild(el('p', 'a2hsp-desc', escapeHtml(D.description)));
    }

    // ---- features ----
    if (shown('features')) {
      var feats = buildFeatures();
      if (feats) sheet.appendChild(feats);
    }

    // ---- screenshots gallery ----
    if (shown('screenshots')) {
      var shots = buildScreenshots();
      if (shots) sheet.appendChild(shots);
    }

    // ---- body: CTA or guide ----
    var body = el('div', 'a2hsp-body');

    if (flow === 'native') {
      var cta = el('button', 'a2hsp-cta', escapeHtml(T.install));
      cta.type = 'button';
      cta.addEventListener('click', function () {
        if (!deferredPrompt) {
          body.innerHTML = '';
          body.appendChild(buildGuide('generic'));
          return;
        }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          deferredPrompt = null;
          if (choice && choice.outcome === 'accepted') {
            store.set('installed', '1');
            closeSheet();
          }
        });
      });
      body.appendChild(cta);

      if (shown('later')) {
        var later = el('button', 'a2hsp-later', escapeHtml(T.later));
        later.type = 'button';
        later.addEventListener('click', dismiss);
        body.appendChild(later);
      }
    } else if (flow === 'inapp') {
      body.appendChild(el('p', 'a2hsp-inapp-msg', escapeHtml(T.inapp)));
      body.appendChild(buildGuide('inapp'));
    } else {
      // Browsers without native prompt: CTA reveals the guide
      var cta2 = el('button', 'a2hsp-cta', escapeHtml(T.install));
      cta2.type = 'button';
      cta2.addEventListener('click', function () {
        cta2.style.display = 'none';
        if (laterBtn) laterBtn.style.display = 'none';
        body.appendChild(buildGuide(flow));
      });
      body.appendChild(cta2);

      var laterBtn = null;
      if (shown('later')) {
        laterBtn = el('button', 'a2hsp-later', escapeHtml(T.later));
        laterBtn.type = 'button';
        laterBtn.addEventListener('click', dismiss);
        body.appendChild(laterBtn);
      }
    }

    sheet.appendChild(body);
    root.appendChild(sheet);

    // swipe-down to close (mobile)
    attachSwipe(sheet);

    return root;
  }

  function attachSwipe(sheet) {
    var startY = null, delta = 0;
    sheet.addEventListener('touchstart', function (e) {
      if (sheet.scrollTop > 0) return;
      startY = e.touches[0].clientY;
      delta = 0;
    }, { passive: true });
    sheet.addEventListener('touchmove', function (e) {
      if (startY == null) return;
      delta = e.touches[0].clientY - startY;
      if (delta > 0) {
        sheet.style.transform = 'translateY(' + delta + 'px)';
        sheet.style.transition = 'none';
      }
    }, { passive: true });
    sheet.addEventListener('touchend', function () {
      sheet.style.transition = '';
      if (delta > 90) {
        dismiss();
      } else {
        sheet.style.transform = '';
      }
      startY = null;
    });
  }

  /* ================= open / close ================= */
  var isOpen = false;

  function openSheet() {
    if (isOpen || isStandalone()) return;
    isOpen = true;
    if (root && root.parentNode) root.parentNode.removeChild(root);
    root = buildSheet();
    document.body.appendChild(root);
    document.documentElement.classList.add('a2hsp-lock');
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        root.classList.add('a2hsp-show');
      });
    });
    store.set('count', (parseInt(store.get('count'), 10) || 0) + 1);
    document.addEventListener('keydown', onKey);
  }

  function onKey(e) {
    if (e.key === 'Escape') dismiss();
  }

  function closeSheet() {
    if (!root) return;
    isOpen = false;
    root.classList.remove('a2hsp-show');
    document.documentElement.classList.remove('a2hsp-lock');
    document.removeEventListener('keydown', onKey);
    var r = root;
    setTimeout(function () {
      if (r && r.parentNode) r.parentNode.removeChild(r);
    }, 350);
  }

  function dismiss() {
    store.set('dismissed', Date.now());
    closeSheet();
  }

  function showSuccess() {
    closeSheet();
    var toast = el('div', 'a2hsp-success', SVG.check + '<span>' + escapeHtml(T.success) + '</span>');
    toast.dir = D.dir || 'rtl';
    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('a2hsp-show'); });
    setTimeout(function () {
      toast.classList.remove('a2hsp-show');
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 400);
    }, 4000);
  }

  /* ================= Public API & triggers ================= */
  window.A2HSPro = {
    show: openSheet,
    hide: closeSheet,
    isInstallable: function () { return installAvailable; },
    isStandalone: isStandalone
  };

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-a2hsp-open]') : null;
    if (t) {
      e.preventDefault();
      openSheet();
    }
  });

  /* ================= Auto show ================= */
  var pendingAutoShow = false;

  if (D.autoShow && canAutoShow()) {
    setTimeout(function () {
      if (isOpen || isStandalone()) return;
      // On Chromium wait a bit more for beforeinstallprompt; other flows open right away
      if (env.chromium && !env.iOS && !deferredPrompt && !env.inApp) {
        pendingAutoShow = true;
        // fallback: open with guide anyway after 4s if event never fires
        setTimeout(function () {
          if (pendingAutoShow && !isOpen) {
            pendingAutoShow = false;
            openSheet();
          }
        }, 4000);
      } else {
        openSheet();
      }
    }, Math.max(0, (D.delay || 0) * 1000));
  }
})();
