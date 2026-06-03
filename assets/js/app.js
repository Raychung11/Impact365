/* IMPACT365 — lightweight client behaviours (no framework, no CDN) */
(function () {
  'use strict';

  /* Mobile nav (public) */
  var mt = document.querySelector('.menu-toggle:not(.dash-toggle)');
  if (mt) {
    mt.addEventListener('click', function () {
      var n = document.querySelector('.nav');
      if (n) n.classList.toggle('open');
    });
  }

  /* Dashboard sidebar drawer */
  var dt = document.querySelector('.dash-toggle');
  if (dt) {
    dt.addEventListener('click', function () {
      document.querySelector('.sidebar').classList.toggle('open');
      var b = document.querySelector('.backdrop');
      if (b) b.classList.toggle('show');
    });
  }
  var bd = document.querySelector('.backdrop');
  if (bd) {
    bd.addEventListener('click', function () {
      document.querySelector('.sidebar').classList.remove('open');
      bd.classList.remove('show');
    });
  }

  /* Confirm destructive actions */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* Copy-to-clipboard (referral links etc.) */
  document.querySelectorAll('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var txt = el.getAttribute('data-copy');
      var done = function () {
        var o = el.textContent;
        el.textContent = 'Copied!';
        setTimeout(function () { el.textContent = o; }, 1500);
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(txt).then(done, done);
      } else {
        var ta = document.createElement('textarea');
        ta.value = txt; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta); done();
      }
    });
  });

  /* Disable submit buttons once clicked to prevent double posts */
  document.querySelectorAll('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      var b = f.querySelector('button[type=submit],input[type=submit]');
      if (b) { b.disabled = true; setTimeout(function(){ b.disabled=false; }, 6000); }
    });
  });

  /* Optional camera QR scanner for event check-in.
     Uses the native BarcodeDetector when available; otherwise the page
     still works via the always-present manual ticket-code input. */
  var scanBtn = document.getElementById('scan-start');
  if (scanBtn && 'BarcodeDetector' in window) {
    scanBtn.classList.remove('hide');
    scanBtn.addEventListener('click', function () {
      var video = document.getElementById('scan-video');
      var det = new window.BarcodeDetector({ formats: ['qr_code'] });
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(function (stream) {
          video.classList.remove('hide');
          video.srcObject = stream;
          video.play();
          var tick = function () {
            det.detect(video).then(function (codes) {
              if (codes.length) {
                var raw = codes[0].rawValue || '';
                var m = raw.match(/[A-Z0-9\-]{6,}/i);
                document.getElementById('ticket_code').value = m ? m[0] : raw;
                stream.getTracks().forEach(function (t) { t.stop(); });
                video.classList.add('hide');
                document.getElementById('checkin-form').submit();
              } else {
                requestAnimationFrame(tick);
              }
            }).catch(function () { requestAnimationFrame(tick); });
          };
          tick();
        }).catch(function () {
          alert('Camera unavailable. Enter the ticket code manually.');
        });
    });
  }
})();
