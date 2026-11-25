/* Conto alla rovescia: aggiorna le cifre allo scatto di ogni secondo e avvisa quando arriva a zero. */

import { padDigits } from '../lib/digits.js';
import { createServerClock } from '../lib/server-clock.js';
import { delayToNextSecond, splitTimeLeft } from '../lib/time-left.js';
import { timerLabel } from '../lib/timer-label.js';

/** Unità nell'ordine in cui compaiono (valori di data-unit). */
const UNITS = ['giorni', 'ore', 'minuti', 'secondi'];

/** I giorni hanno due celle: oltre 99 il riquadro resta fermo a 99. */
const MAX_DAYS = 99;

/**
 * @param {HTMLElement} root elemento con data-countdown, data-target e data-now (ms)
 * @param {{ reduceMotion: boolean, onZero: () => void }} options
 */
export function initCountdown(root, { reduceMotion, onZero }) {
  const target = Number(root.getAttribute('data-target'));
  // L'orologio di riferimento è quello del server, non quello del dispositivo.
  const now = createServerClock(Number(root.getAttribute('data-now')));
  const timer = root.querySelector('[role="timer"]');
  let lastMinute = -1;

  const setDigits = (unit, value) => {
    const cells = root.querySelectorAll(`[data-unit="${unit}"] .fs-cd__cell`);
    const text = padDigits(value, cells.length);
    cells.forEach((cell, index) => {
      const incoming = cell.querySelector('.fs-cd__new');
      const outgoing = cell.querySelector('.fs-cd__old');
      if (incoming.textContent === text[index]) return;
      outgoing.textContent = incoming.textContent;
      incoming.textContent = text[index];
      if (!reduceMotion) {
        // Riavvia l'animazione di cambio cifra: la lettura di offsetWidth forza il ricalcolo.
        cell.classList.remove('is-changing');
        void cell.offsetWidth;
        cell.classList.add('is-changing');
      }
    });
  };

  const tick = () => {
    const left = target - now();
    if (left <= 0) {
      UNITS.forEach((unit) => setDigits(unit, 0));
      onZero();
      return;
    }

    const { days, hours, minutes, seconds } = splitTimeLeft(left);
    setDigits('giorni', Math.min(days, MAX_DAYS));
    setDigits('ore', hours);
    setDigits('minuti', minutes);
    setDigits('secondi', seconds);

    if (timer && minutes !== lastMinute) {
      lastMinute = minutes;
      timer.setAttribute('aria-label', timerLabel({ days, hours, minutes }));
    }
    // Prossimo aggiornamento allo scatto del secondo.
    window.setTimeout(tick, delayToNextSecond(left));
  };

  tick();

  // Al ritorno sulla scheda i timer possono essere in ritardo: riallinea subito.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !root.hidden && target - now() <= 0) onZero();
  });
}
