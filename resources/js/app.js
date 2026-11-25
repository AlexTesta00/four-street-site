/* 4Street · script del sito. Qui si collegano soltanto le funzioni della pagina.
   La pagina funziona anche senza JavaScript: lo script aggiunge il conto che scorre e il passaggio
   allo stato "aperti" allo zero, il riepilogo della fascia scelta, la barra delle azioni che si fa
   da parte, la comparsa delle sezioni dove il CSS non basta e la copia dell'indirizzo.
   Ogni funzione non fa nulla se nella pagina manca il suo elemento (data-*). */

import { initActionBar } from './features/action-bar.js';
import { initCopyAddress } from './features/copy-address.js';
import { initCountdown } from './features/countdown.js';
import { createOpenSwitch } from './features/page-state.js';
import { initReveal } from './features/reveal.js';
import { initSlotPicker } from './features/slot-picker.js';

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const countdown = document.querySelector('[data-countdown]');
if (countdown) {
  const open = createOpenSwitch({
    leaving: countdown,
    title: countdown.getAttribute('data-open-title'),
    reduceMotion,
  });
  initCountdown(countdown, { reduceMotion, onZero: open });
}

initSlotPicker(document);
initActionBar(document);
initReveal(document, { reduceMotion });
initCopyAddress(document);
