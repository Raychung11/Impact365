/* IMPACT365 — landing page interactions (vanilla, no deps, no CDN) */
(function () {
  'use strict';

  var reduce = window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Count-up animation for [data-count] ---- */
  function countUp(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduce || target === 0) {
      el.textContent = target.toLocaleString();
      return;
    }
    var start = null, dur = 1400;
    function step(ts) {
      if (!start) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.floor(eased * target).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(step);
  }

  /* ---- Scroll reveal + trigger counters within ---- */
  var reveals = document.querySelectorAll('.reveal');
  function activate(sec) {
    sec.classList.add('in');
    sec.querySelectorAll('[data-count]').forEach(function (c) {
      if (!c._done) { c._done = true; countUp(c); }
    });
  }
  if ('IntersectionObserver' in window && !reduce && reveals.length) {
    // Opt in to the hidden-then-animate state only now that we know JS runs.
    document.documentElement.classList.add('js-reveal');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { activate(en.target); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(function (s) { io.observe(s); });
    // Safety net: if anything is still hidden shortly after load, show it.
    window.addEventListener('load', function () {
      setTimeout(function () {
        reveals.forEach(function (s) {
          var r = s.getBoundingClientRect();
          if (r.top < window.innerHeight && !s.classList.contains('in')) activate(s);
        });
      }, 1200);
    });
  } else {
    reveals.forEach(activate);
  }

  /* ---- Hero word rotator ---- */
  var rot = document.querySelector('.rotator');
  if (rot && !reduce) {
    var words = (rot.getAttribute('data-words') || '').split('|').filter(Boolean);
    var span = rot.querySelector('.rotator-word');
    if (words.length > 1 && span) {
      var idx = 0;
      setInterval(function () {
        span.classList.add('out');
        setTimeout(function () {
          idx = (idx + 1) % words.length;
          span.textContent = words[idx];
          span.classList.remove('out');
        }, 350);
      }, 2600);
    }
  }

  /* ---- "How it works" stepper tabs ---- */
  var stepper = document.getElementById('stepper');
  if (stepper) {
    var tabs = stepper.querySelectorAll('.stepper-tab');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        stepper.querySelectorAll('.stepper-panel').forEach(function (p) {
          p.classList.remove('active');
          p.hidden = true;
        });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        var panel = document.getElementById(tab.getAttribute('data-panel'));
        if (panel) { panel.hidden = false; panel.classList.add('active'); }
      });
    });
  }

  /* ---- FAQ accordion ---- */
  document.querySelectorAll('.faq-q').forEach(function (q) {
    q.addEventListener('click', function () {
      var open = q.getAttribute('aria-expanded') === 'true';
      q.setAttribute('aria-expanded', open ? 'false' : 'true');
      q.parentElement.classList.toggle('open', !open);
    });
  });

  /* ---- Smooth scroll for in-page anchors ---- */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var id = a.getAttribute('href');
      if (id.length < 2) return;
      var t = document.querySelector(id);
      if (t) {
        e.preventDefault();
        t.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      }
    });
  });
})();
