/* 4Street · script del sito. Qui si collegano soltanto le funzioni della pagina.
   La pagina funziona anche senza JavaScript: lo script aggiunge il conto che scorre,
   il passaggio alla mappa allo zero e la copia dell'indirizzo. */

import { initCopyAddress } from './features/copy-address.js';
import { initCountdown } from './features/countdown.js';
import { createLiveSwitch } from './features/live-state.js';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const countdown = document.querySelector('[data-countdown]');
if (countdown) {
  const goLive = createLiveSwitch({
    countdown,
    live: document.getElementById('live'),
    reduceMotion,
  });
  initCountdown(countdown, { reduceMotion, onZero: goLive });
}

initCopyAddress(document);
