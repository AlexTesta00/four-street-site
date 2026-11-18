/* 4Street · conto alla rovescia, passaggio alla mappa allo zero, copia dell'indirizzo. */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Mappa: l'iframe si carica solo quando serve ---------- */
  function loadMap() {
    var frame = document.querySelector('.fs-map__frame[data-src]');
    if (frame) {
      frame.src = frame.getAttribute('data-src');
      frame.removeAttribute('data-src');
    }
  }

  /* ---------- Countdown ---------- */
  var countdown = document.querySelector('[data-countdown]');
  var live = document.getElementById('live');

  if (countdown) {
    var target = Number(countdown.getAttribute('data-target'));
    // L'orologio di riferimento è quello del server, non quello del dispositivo.
    var skew = Number(countdown.getAttribute('data-now')) - Date.now();
    var timer = countdown.querySelector('[role="timer"]');
    var lastMinute = -1;

    var setDigits = function (unit, value) {
      var cells = countdown.querySelectorAll('[data-unit="' + unit + '"] .fs-cd__cell');
      var text = String(value);
      while (text.length < cells.length) text = '0' + text;
      text = text.slice(-cells.length);
      for (var i = 0; i < cells.length; i++) {
        var cell = cells[i];
        var incoming = cell.querySelector('.fs-cd__new');
        var outgoing = cell.querySelector('.fs-cd__old');
        if (incoming.textContent === text[i]) continue;
        outgoing.textContent = incoming.textContent;
        incoming.textContent = text[i];
        if (!reduceMotion) {
          // Riavvia l'animazione di cambio cifra.
          cell.classList.remove('is-changing');
          void cell.offsetWidth;
          cell.classList.add('is-changing');
        }
      }
    };

    var goLive = function () {
      if (!live || !live.hidden) return;
      var swap = function () {
        countdown.hidden = true;
        live.hidden = false;
        loadMap();
        document.title = live.getAttribute('data-title') || document.title;
        window.scrollTo(0, 0);
      };
      if (reduceMotion) {
        swap();
      } else {
        countdown.classList.add('is-leaving');
        window.setTimeout(swap, 620);
      }
    };

    var tick = function () {
      var left = target - (Date.now() + skew);
      if (left <= 0) {
        setDigits('giorni', 0);
        setDigits('ore', 0);
        setDigits('minuti', 0);
        setDigits('secondi', 0);
        goLive();
        return;
      }
      var seconds = Math.ceil(left / 1000);
      var days = Math.floor(seconds / 86400);
      var hours = Math.floor((seconds % 86400) / 3600);
      var minutes = Math.floor((seconds % 3600) / 60);
      setDigits('giorni', Math.min(days, 99));
      setDigits('ore', hours);
      setDigits('minuti', minutes);
      setDigits('secondi', seconds % 60);

      if (timer && minutes !== lastMinute) {
        lastMinute = minutes;
        timer.setAttribute('aria-label', 'Mancano ' + days + ' giorni, ' + hours + ' ore e ' + minutes + ' minuti all\'inaugurazione');
      }
      // Prossimo aggiornamento allo scatto del secondo.
      window.setTimeout(tick, (left % 1000) + 20);
    };

    tick();
    // Al ritorno sulla scheda i timer possono essere in ritardo: riallinea subito.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && !countdown.hidden) {
        var left = target - (Date.now() + skew);
        if (left <= 0) goLive();
      }
    });
  }

  /* ---------- Copia indirizzo ---------- */
  var copyButtons = document.querySelectorAll('[data-copy]');
  Array.prototype.forEach.call(copyButtons, function (button) {
    var label = button.querySelector('[data-copy-label]');
    var original = label ? label.textContent : '';
    var resetTimer;

    var done = function (ok) {
      if (!label) return;
      label.textContent = ok ? 'Copiato' : 'Copia non riuscita';
      window.clearTimeout(resetTimer);
      resetTimer = window.setTimeout(function () { label.textContent = original; }, 2000);
    };

    button.addEventListener('click', function () {
      var text = button.getAttribute('data-copy');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(false); });
        return;
      }
      var field = document.createElement('textarea');
      field.value = text;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.appendChild(field);
      field.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (error) { ok = false; }
      document.body.removeChild(field);
      done(ok);
    });
  });
})();
